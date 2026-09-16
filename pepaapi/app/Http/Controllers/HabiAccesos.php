<?php

namespace App\Http\Controllers;


use App\HabiAcceso;
use App\HabiCredPersona;
use App\HabiCredSectores;
use App\Helpers\ConfigParametro;
use Illuminate\Support\Facades\Cache;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HabiAccesos extends Controller
{
    public static function syncSnapshot(
        int $cod_credencial,
        array $newTemas
    ): void {

        $oldTemas = DB::table('habiAccesoSnap')
            ->where('cod_credencial', $cod_credencial)
            ->pluck('cod_tema')
            ->toArray();

        $topicsAdded =
            array_diff($newTemas, $oldTemas);

        $topicsRemoved =
            array_diff($oldTemas, $newTemas);

        foreach ($topicsAdded as $tema) {

            DB::table('habiAccesoSnap')
                ->insertOrIgnore([
                    'cod_tema' => $tema,
                    'cod_credencial' => $cod_credencial,
                ]);

            DB::table('habiCredCambio')
                ->insert([
                    'cod_tema' => $tema,
                    'cod_credencial' => $cod_credencial,
                    'operation' => 'ADD'
                ]);
        }

        foreach ($topicsRemoved as $tema) {

            DB::table('habiAccesoSnap')
                ->where('cod_tema', $tema)
                ->where('cod_credencial', $cod_credencial)
                ->delete();

            DB::table('habiCredCambio')
                ->insert([
                    'tema' => $tema,
                    'cod_credencial' => $cod_credencial,
                    'operation' => 'DEL'
                ]);
        }
    }


    function fcCardToWiegand26(int $fc, int $card): int
    {
        $data24 = (($fc & 0xFF) << 16) | ($card & 0xFFFF);

        // Paridad par sobre los primeros 12 bits
        $even = 0;
        for ($i = 12; $i < 24; $i++) {
            $even ^= (($data24 >> $i) & 1);
        }

        // Paridad impar sobre los últimos 12 bits
        $odd = 1;
        for ($i = 0; $i < 12; $i++) {
            $odd ^= (($data24 >> $i) & 1);
        }

        return ($even << 25) | ($data24 << 1) | $odd;
    }

    public function getLastUpdate()
    {
        $lastUpdate = Cache::get('HabiAccesoLastUpdate');
        if ($lastUpdate == "") {
            $lastUpdate = Carbon::now()->format('Y-m-d H:i:s');
            Cache::forever('HabiAccesoLastUpdate', $lastUpdate);
        }
        return $lastUpdate;
    }

    public function getHabiAccesoSync(Request $request)
    {
        $page = $request->input('page');
        $pageSize = $request->input('pageSize');
        return HabiAcceso::select()->simplePaginate($pageSize, ['*'], 'page', $page);
    }

    public function getHabiAccesoPorTema(Request $request)
    {
        $tema = $request->input('tema');
        $baseTema = strtolower(ConfigParametro::get("TEMA_LOCAL", false));
        $tema = rtrim($baseTema, '/') . '/' . ltrim($tema, '/');

        $since = (int) $request->input('since', -1);
        $changes = array();
        $lastChangeId = 0;
        $simular = $request->input('simular') ? true:false;

        if ($simular) {

            $since = (int) $request->input('since', -1);

            if ($since == -1) {

                $changes = [];

                for ($i = 1; $i <= 30000; $i++) {

                    $row = new \stdClass();
                    $row->c = random_int(1, 67108863);
                    $row->o = 'ADD';

                    $changes[] = $row;
                }

                return response()->json([
                    'last_change_id' => 1000,
                    'changes' => $changes
                ]);
            }

            $changes = [];

            $changeId = $since;

            // 500 ADD
            for ($i = 0; $i < 500; $i++) {

                $row = new \stdClass();
                $row->id = ++$changeId;
                $row->c = random_int(1, 67108863);
                $row->o = 'ADD';

                $changes[] = $row;
            }

            // 200 DEL
            for ($i = 0; $i < 200; $i++) {

                $row = new \stdClass();
                $row->id = ++$changeId;
                $row->c = random_int(1, 67108863);
                $row->o = 'DEL';

                $changes[] = $row;
            }

            // Casos conflictivos para probar
            for ($i = 0; $i < 50; $i++) {

                $card = random_int(1, 67108863);

                $row = new \stdClass();
                $row->id = ++$changeId;
                $row->c = $card;
                $row->o = 'ADD';
                $changes[] = $row;

                $row = new \stdClass();
                $row->id = ++$changeId;
                $row->c = $card;
                $row->o = 'DEL';
                $changes[] = $row;
            }

            return response()->json([
                'last_change_id' => $changeId,
                'changes' => $changes
            ]);
        }




        /*
        if (Cache::get("PANEL_$tema", -2)==$since)
        return response()->json([
            'last_change_id' => $lastChangeId,
            'changes' => $changes->map(function ($row) {
                unset($row->id);
                unset($row->cod_tema);
                unset($row->created_at);
                return $row;
            })
        ]);
        */

        if ($since != -1) {
            $changes = DB::table('habiCredCambio')
                ->whereIn('cod_tema', array($tema . "/9/1", $tema . "/9/2"))
                ->where('id', '>', $since)
                ->orderBy('id')
                ->get()
                ->map(function ($row) {
                    $credStr = sprintf('%08d', $row->cod_credencial);
                    $fc = (int) substr($credStr, 0, -5);
                    $card = (int) substr($credStr, -5);
                    unset($row->cod_credencial);
                    $row->c = $this->fcCardToWiegand26($fc, $card);
                    $row->o = $row->operation;
                    unset($row->operation);
                    return $row;
                })
                ->keyBy('c')
                ->sortBy('id')
                ->values();
            $lastRow = $changes->last();
            $lastChangeId = $lastRow ? $lastRow->id : $since;
        } else {
            $changes = DB::table('habiAccesoSnap')
                ->whereIn('cod_tema', array($tema . "/9/1", $tema . "/9/2"))
                ->orderBy('cod_credencial')
                ->get()
                ->map(function ($row) {
                    $credStr = sprintf('%08d', $row->cod_credencial);
                    $fc = (int) substr($credStr, 0, -5);
                    $card = (int) substr($credStr, -5);
                    unset($row->cod_credencial);
                    $row->c = $this->fcCardToWiegand26($fc, $card);
                    $row->o = "ADD";
                    return $row;
                });
            $lastChangeId = DB::table('habiCredCambio')
                ->whereIn('cod_tema', [$tema . "/9/1", $tema . "/9/2"])
                ->max('id') ?? 0;


        }
        //Cache::forever("PANEL_$tema", $lastChangeId);

        return response()->json([
            'last_change_id' => $lastChangeId,
            'changes' => $changes->map(function ($row) {
                unset($row->id);
                unset($row->cod_tema);
                unset($row->created_at);
                return $row;
            })
        ]);
    }

    public function getHabiAccesoPorTemaOld(Request $request)
    {
        $tema = $request->input('tema');
        $tema = str_replace("/", "\\\\/", $tema);

        return HabiAcceso::select('cod_credencial')
            ->where('tipo_habilitacion', 'P')
            ->where('json_temas', 'LIKE', "%{$tema}%")
            ->get()
            ->map(function ($row) {
                $credStr = sprintf('%08d', $row->cod_credencial);
                $fc = (int) substr($credStr, 0, -5);
                $card = (int) substr($credStr, -5);
                unset($row->cod_credencial);
                $row->card_number = $this->fcCardToWiegand26($fc, $card);
                return $row;
            });
    }

    public static function delCredencialAcceso(array $cod_credencial_arr)
    {
        $rows = DB::table('habiAccesoSnap')
            ->whereIn(
                'cod_credencial',
                $cod_credencial_arr
            )
            ->get([
                'cod_tema',
                'cod_credencial'
            ]);

        $changes = [];

        foreach ($rows as $row) {

            $changes[] = [
                'cod_tema' => $row->cod_tema,
                'cod_credencial' => $row->cod_credencial,
                'operation' => 'DEL',
                'created_at' => now()
            ];
        }

        if (!empty($changes)) {

            DB::table('habiCredCambio')
                ->insert($changes);
        }

        DB::table('habiAccesoSnap')
            ->whereIn(
                'cod_credencial',
                $cod_credencial_arr
            )
            ->delete();

        HabiAcceso::whereIn(
            'cod_credencial',
            $cod_credencial_arr
        )->delete();
    }

    public static function checkhabiAcceso($checkFirst = false)
    {
        //SI NO EXISTE LA TABLA HABIACCESO, LA CREA
        if ($checkFirst) {
            $selHabiAcceso = self::select()->first();
            if (!empty($selHabiAcceso))
                return;
        }

        $stm_actual = Carbon::now()->format('Y-m-d H:i:s.u');
        $user = Auth::user();
        $cod_usuario = (isset($user['cod_usuario'])) ? $user['cod_usuario'] : "interno";
        //$ip = Request::ip();
        $ip = '';

        $vaLectores = ConfigParametro::getTemas("LECTOR");
        $selHabiAcceso = HabiCredPersona::select(
            'habiCredPersona.cod_credencial',
            'habiCredPersona.cod_ou_hab',
            'habiCredPersona.cod_persona_contacto',
            'habiCredPersona.cod_ou_emisora',
            'maesAliasCred.ref_credencial',
            'habiCredPersona.cod_persona',
            'habiCredPersona.tipo_habilitacion',
            'habiCredPersona.stm_habilitacion_hasta',
            'maesPersonas.nom_persona',
            'maesPersonas.ape_persona',
            'maesPersonas.cod_sexo',
            'habiCredPersona.obs_habilitacion',
            'maesPersonas.cod_tipo_doc',
            'maesPersonas.nro_documento',
            'habiCredGrupo.cod_grupo',
            'maesUnidadesOrganiz.nom_ou as nom_ou_hab',
            'personaContacto.nom_persona as nom_persona_contacto',
            'personaContacto.ape_persona as ape_persona_contacto',
            'habiCredPersona.cod_esquema_acceso'
        )
            ->leftjoin('maesAliasCred', 'maesAliasCred.cod_credencial', '=', 'habiCredPersona.cod_credencial')
            ->leftjoin('maesPersonas', 'maesPersonas.cod_persona', '=', 'habiCredPersona.cod_persona')
            ->leftjoin('habiCredGrupo', 'habiCredGrupo.cod_credencial', '=', 'habiCredPersona.cod_credencial')
            ->leftjoin('maesUnidadesOrganiz', 'maesUnidadesOrganiz.cod_ou', '=', 'habiCredPersona.cod_ou_hab')
            ->leftjoin('maesPersonas as personaContacto', 'personaContacto.cod_persona', '=', 'habiCredPersona.cod_persona')
            ->get();

        foreach ($selHabiAcceso as $row) {
            $cod_credencial = $row['cod_credencial'];
            $credencialesProcesadas[] = $row['cod_credencial'];
            $sectoresSel = HabiCredSectores::select('cod_sector')->where('cod_credencial', $cod_credencial)->get();
            $vaTemas = array();
            foreach ($sectoresSel as $cod) {
                $cod_sector = $cod['cod_sector'];
                foreach ($vaLectores as $cod_tema => $datos_tema) {
                    if ($datos_tema['cod_sector'] == $cod_sector) {
                        $vaTemas[$cod_tema] = $cod_tema;
                    }
                }
            }
            $json_temas = $vaTemas;
            self::syncSnapshot($row['cod_credencial'], array_keys($json_temas));

            HabiAcceso::updateOrCreate(
                [
                    'cod_credencial' => $row['cod_credencial']
                ],
                [
                    'ref_credencial' => $row['ref_credencial'],
                    'cod_persona' => $row['cod_persona'],
                    'nom_persona' => $row['nom_persona'],
                    'ape_persona' => $row['ape_persona'],
                    'cod_sexo' => $row['cod_sexo'],
                    'cod_tipo_doc' => $row['cod_tipo_doc'],
                    'nro_documento' => $row['nro_documento'],
                    'tipo_habilitacion' => $row['tipo_habilitacion'],
                    'obs_habilitacion' => $row['obs_habilitacion'],
                    'cod_grupo' => $row['cod_grupo'],
                    'cod_ou_hab' => $row['cod_ou_hab'],
                    'nom_ou_hab' => $row['nom_ou_hab'],
                    'cod_persona_contacto' => $row['cod_persona_contacto'],
                    'nom_persona_contacto' => $row['nom_persona_contacto'],
                    'ape_persona_contacto' => $row['ape_persona_contacto'],
                    'cantidad_ingresos' => 0,
                    'json_temas' => $json_temas,
                    'cod_esquema_acceso' => $row['cod_esquema_acceso'],
                    'stm_habilitacion_hasta' => $row['stm_habilitacion_hasta'],
                    'aud_usuario_ingreso' => $cod_usuario,
                    'aud_stm_ingreso' => $stm_actual,
                    'aud_ip_ingreso' => $ip
                ]
            );
        }

        Cache::forever("HabiAccesoLastUpdate", Carbon::now()->format('Y-m-d H:i:s'));
        return true;
    }
}
