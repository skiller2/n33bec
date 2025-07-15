<?php

use App\Providers\RouteServiceProvider;

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
  |--------------------------------------------------------------------------
  | API Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register API routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | is assigned the "api" middleware group. Enjoy building your API!
  |
 */

/*
  Route::get('/user', function (Request $request) {
  return $request->user();
  });
 */

Route::group(['middleware' => 'throttle:3,1,signin'],function () {
    Route::post('/v1/usuarios/signin', 'Usuarios@signin'); // ->middleware('auth:api')
});

Route::group(['middleware' => 'throttle:5000,1,pool'],function () {
    Route::get('/v1/displaysucesos/lista/{export?}', 'MoviDisplayTemas@index');
    Route::get('/v1/parametros/getParametro/{den_parametro}', 'Parametros@getParametro');
    Route::get('/v1/displaysucesos/listasec', 'DisplaySucesos@getLista');
});

Route::group(['middleware' => 'throttle:2500,1,normal'], function () {

//Route::middleware('customthrottle:120,1')->group(function () {
//    Route::post('/v1/usuarios/signup', 'Usuarios@store'); // ->middleware('auth:api')
    Route::get('/v1/display_area54/{cod_tema}', 'IOAPI@DisplayArea54');
    Route::post('/v1/movieventos/evento', 'MoviEventos@altaEventoExt');
    Route::get('/v1/io/{id_disp_origen}/{io_name}', 'IOAPI@getIOData');
    Route::get('/v1/temas/getEstadosLeds', 'Temas@getEstadosLeds');
    Route::get('/v1/habiaccesos/getLastUpdate', 'HabiAccesos@getLastUpdate');
    Route::get('/v1/habiaccesos/sync', 'HabiAccesos@getHabiAccesoSync');
    Route::get('/v1/sectores/sync', 'Sectores@getSectoresSync');
    Route::get('/v1/sectoresxou/sync', 'Sectores@getSectoresxOUSync');
    Route::get('/v1/esquemas/sync', 'Esquemas@getEsquemasSync');
    Route::get('/v1/unidadesorganiz/sync', 'UnidadesOrganizativas@getOUSync');
    Route::get('/v1/temas/sync', 'Temas@getTemasSync');
    Route::post('/v1/movimientos/sync/permanentes', 'Movimientos@setPermanentesSync');
    Route::post('/v1/movimientos/sync/temporales', 'Movimientos@setTemporalesSync');
    Route::post('/v1/movimientos/sync/rechazados', 'Movimientos@setRechazadosSync');
    Route::get('/v1/movimientos/disppermanentes/lista/{export?}', 'Movimientos@indexPermanentesDisplay');
    Route::get('/v1/movimientos/disppermanentes/gridOptions/{version?}', 'Movimientos@gridOptionsPermanentesDisplay');

    Route::post('/v1/movimientos/evento', 'Movimientos@EventoCred');
    Route::post('/v1/registros/sync', 'Asis\Registros@syncAsis');

    Route::get('/v1/displaysector/sector/{cod_sector}', 'DisplaySector@getSectorCants');
    Route::get('/v1/displaysector/persona/{cod_credencial}', 'DisplaySector@getPersona');
    Route::get('/v1/displaysector/foto/{cod_credencial}', 'DisplaySector@getFoto');
    Route::get('/v1/displaysector/lista/{cod_sector}', 'DisplaySector@getLista');


    Route::get('/v1/displaysucesos/temadetalle/{cod_tema}', 'Temas@getTemaDetalle');
    Route::get('/v1/displaysucesos/sectortemasdetalle/{cod_tema_sector}', 'Temas@getTemasDetalleSector');
    Route::get('/v1/displaysucesos/sectordetalle/{cod_tema_sector}', 'Sectores@getSectorDetalle');
    Route::get('/v1/displaysucesos/subtemasdetalle/{cod_tema}', 'DisplaySucesos@getSubtemasDetalle');

//    Route::get('/v1/displaysucesos/temaimg/{cod_tema}', 'TemasImgs@getImg');
//    Route::get('/v1/displaysucesos/temaimgdata/{cod_tema}', 'TemasImgs@getImgData');

    Route::get('/v1/displaysucesos/sectorimgdata/{cod_tema_sector}/{img_hash}', 'ImagenesSectores@getImgData');
    Route::get('/v1/displaysucesos/temaimgdata/{cod_tema}/{img_hash}', 'ImagenesTemas@getImgData');
    Route::get('/v1/displaysucesos/sectorimg/{cod_sector}/{img_hash}', 'ImagenesSectores@getImg');
    Route::get('/v1/wsauth', 'Auth\LoginController@websocketAuth');

    Route::post('/v1/habilitacionesxou', 'Habilitaciones@storeOU');

    Route::post('/v1/displaysucesos/remoto/resetear', 'DisplaySucesos@resetearRemote');
    Route::post('/v1/displaysucesos/remoto/restaurarTema', 'MoviDisplayTemas@restaurarTemaRemote');
    Route::post('/v1/displaysucesos/cmdcentral', 'MoviDisplayTemas@cmdCentral');

});

Route::group(['middleware' => array('jwt.refresh')], function () {
    Route::post('/v1/refreshtoken', 'RefreshToken@refresh');
});

Route::group(['middleware' => array('jwt.auth', 'pepa.authorize')], function () {

    //PREFERENCIAS
    Route::post('/v1/preferencias', 'Preferencias@store');
    Route::get('/v1/preferencias/{cod_preferencia?}', 'Preferencias@detalle');

    //MOVIMIENTOS
    Route::get('/v1/movimientos/permanentes/lista/{export?}', 'Movimientos@indexPermanentes');
    Route::get('/v1/movimientos/temporales/lista/{export?}', 'Movimientos@indexTemporales');
    Route::get('/v1/movimientos/rechazados/lista/{export?}', 'Movimientos@indexRechazados');
    Route::get('/v1/movimientos/permanentes/gridOptions/{version?}', 'Movimientos@gridOptionsPermanentes');
    Route::get('/v1/movimientos/temporales/gridOptions/{version?}', 'Movimientos@gridOptionsTemporales');
    Route::get('/v1/movimientos/rechazados/gridOptions/{version?}', 'Movimientos@gridOptionsRechazados');
    Route::get('/v1/movimientos/permanentes/{stm_movimiento}/{ou_sel?}', 'Movimientos@detallePermanentes');
    Route::get('/v1/movimientos/temporales/{stm_movimiento}/{ou_sel?}', 'Movimientos@detalleTemporales');
    Route::get('/v1/movimientos/rechazados/{stm_movimiento}/{ou_sel?}', 'Movimientos@detalleRechazados');
    Route::get('/v1/movimientos/dashboard/{cant_filas?}', 'Movimientos@dashboard');
    Route::post('/v1/movimientos/test', 'Movimientos@test');

    //PERSONAS
    Route::get('/v1/personas/lista/{export?}', 'Personas@index');
    Route::get('/v1/personas/gridOptions/{version?}', 'Personas@gridOptions');
    Route::get('/v1/personas/{cod_persona}/{cod_ou?}', 'Personas@detalle');
    Route::put('/v1/personas', 'Personas@update');
    Route::post('/v1/personas', 'Personas@store');
    Route::delete('/v1/personas/{cod_persona}', 'Personas@delete');

    //IMAGENES
    Route::get('/v1/imagenes/{tipo_imagen}/{cod_imagen}', 'Imagenes@detalle');

    //IMAGENES
    Route::get('/v1/sectoresimgs/{cod_sector}/{img_hash}', 'ImagenesSectores@detalle');
    Route::get('/v1/temasimgs/{cod_tema}/{img_hash}', 'ImagenesTemas@detalle');

    //APTOS FISICOS
    Route::get('/v1/aptosfisicos/lista/{export?}', 'AptosFisicos@index');
    Route::get('/v1/aptosfisicos/gridOptions/{version?}', 'AptosFisicos@gridOptions');
    Route::get('/v1/aptosfisicos/getAbilities', 'AptosFisicos@getAbilities');
    Route::get('/v1/aptosfisicos/{cod_persona}', 'AptosFisicos@detalle');
    Route::put('/v1/aptosfisicos', 'AptosFisicos@update');
    Route::delete('/v1/aptosfisicos/{cod_persona}', 'AptosFisicos@delete');

    //USUARIOS
    Route::get('/v1/usuarios/lista/{export?}', 'Usuarios@index');
    Route::get('/v1/usuarios/gridOptions/{version?}', 'Usuarios@gridOptions');
    Route::get('/v1/usuarios/getAbilities', 'Usuarios@getAbilities');
    Route::get('/v1/usuarios/getPersona/{persona?}', 'Usuarios@getPersona');
    Route::get('/v1/usuarios/getPersonaxOU/{persona?}/{cod_ou?}', 'Usuarios@getPersonaxOU');
    Route::get('/v1/usuarios/{cod_usuario}/{ou_sel?}', 'Usuarios@detalle');
    Route::get('/v1/usuarios/validaDispositivo/{cod_dispositivo}', 'Usuarios@validaDispositivo');
    Route::put('/v1/usuarios', 'Usuarios@update');
    Route::post('/v1/usuarios', 'Usuarios@store');
    Route::delete('/v1/usuarios/{cod_usuario}', 'Usuarios@delete');

    //HABICREDPERSONAS
    Route::get('/v1/habilitaciones/lista/{export?}', 'Habilitaciones@index');
    Route::get('/v1/habilitaciones/gridOptions/{version?}', 'Habilitaciones@gridOptions');
    Route::get('/v1/habilitaciones/defaults', 'Visitas@getDefaults');
    Route::post('/v1/habilitaciones/upload', 'Habilitaciones@upload');
    Route::post('/v1/habilitaciones/importa', 'Habilitaciones@importa');
    Route::get('/v1/habilitaciones/{cod_credencial}/{ou_sel?}', 'Habilitaciones@detalle');
    Route::put('/v1/habilitaciones', 'Habilitaciones@store');
    Route::post('/v1/habilitaciones', 'Habilitaciones@store');
    Route::get('/v1/habilitaciones/valida/{campo}/{valor}/{cod_ou}', 'Habilitaciones@valida');
    Route::get('/v1/habilitaciones/estadocred/{cod_credencial_bus}/{tipo_habilitacion_bus}', 'Habilitaciones@estadoCred');
    Route::delete('/v1/habilitaciones/{cod_credencial}', 'Habilitaciones@delete');

    //STOCK CREDENCIALES
    Route::get('/v1/stockcredenciales/lista/{export?}', 'StockCredenciales@index');
    Route::get('/v1/stockcredenciales/gridOptions/{version?}', 'StockCredenciales@gridOptions');
    Route::get('/v1/stockcredenciales/{cod_credencial}/{ou_sel?}', 'StockCredenciales@detalle');
    Route::put('/v1/stockcredenciales', 'StockCredenciales@update');
    Route::post('/v1/stockcredenciales', 'StockCredenciales@store');
    Route::delete('/v1/stockcredenciales/{cod_credencial}', 'StockCredenciales@delete');

    //COMPONENTES

    //TEMAS
    Route::get('/v1/temas/lista/{export?}', 'Temas@index');
    Route::get('/v1/temas/gridOptions/{version?}', 'Temas@gridOptions');
    Route::get('/v1/temasnr/gridOptions/{version?}', 'Temas@gridOptionsnr');
    Route::get('/v1/temasnr/lista/{export?}', 'Temas@indexnr');
    Route::get('/v1/temas/getLectores/{ind_movimiento?}', 'Temas@getLectores');
    Route::get('/v1/temas/getTemas/{ind_tipo_uso?}', 'Temas@getTemas');
    Route::post('/v1/temas/sendCommand', 'Temas@sendCommand');
    Route::post('/v1/temas/runEvent', 'Temas@runEvent');
    Route::post('/v1/temas/setOperationMode', 'Temas@setOperationMode');
    Route::get('/v1/temas/{cod_tema}', 'Temas@detalle');
    Route::put('/v1/temas', 'Temas@update');
    Route::post('/v1/temas', 'Temas@store');
    Route::delete('/v1/temas/{cod_tema}', 'Temas@delete');
    Route::delete('/v1/temasnr/{cod_tema}', 'Temas@deletenr');

    //SECTORES
    Route::get('/v1/sectores/lista/{export?}', 'Sectores@index');
    Route::get('/v1/sectores/gridOptions/{version?}', 'Sectores@gridOptions');
    Route::get('/v1/sectores/combo/{xusuario?}', 'Sectores@getSectores');
    Route::get('/v1/sectores/comboxou/{cod_ou?}/{xusuario?}', 'Sectores@getSectoresxOU');
    Route::get('/v1/sectores/{cod_sector}/{ou_sel?}', 'Sectores@detalle');
    Route::get('/v1/sectores/imgdata/{cod_sector}/{img_hash}', 'ImagenesSectores@getImgData');

    Route::put('/v1/sectores', 'Sectores@update');
    Route::post('/v1/sectores', 'Sectores@store');
    Route::delete('/v1/sectores/{cod_sector}', 'Sectores@delete');

    //Organizaciones
    Route::get('/v1/unidadesorganiz/combo', 'UnidadesOrganizativas@getOU');
    Route::get('/v1/unidadesorganiz/comboxusuario', 'UnidadesOrganizativas@getOUxUsuario');
    Route::get('/v1/unidadesorganiz/lista/{export?}', 'UnidadesOrganizativas@index');
    Route::get('/v1/unidadesorganiz/gridOptions/{version?}', 'UnidadesOrganizativas@gridOptions');
    Route::get('/v1/unidadesorganiz/{cod_ou}/{ou_sel?}', 'UnidadesOrganizativas@detalle');
    Route::put('/v1/unidadesorganiz', 'UnidadesOrganizativas@update');
    Route::post('/v1/unidadesorganiz', 'UnidadesOrganizativas@store');
    Route::delete('/v1/unidadesorganiz/{cod_ou}', 'UnidadesOrganizativas@delete');

    //VISITAS
    Route::get('/v1/visitas/lista/{export?}', 'Visitas@index');
    Route::get('/v1/visitas/gridOptions/{version?}', 'Visitas@gridOptions');
    Route::get('/v1/visitas/defaults', 'Visitas@getDefaults');
    Route::get('/v1/visitas/{cod_credencial}/{ou_sel?}', 'Visitas@detalle');
    Route::put('/v1/visitas', 'Visitas@update');
    Route::post('/v1/visitas', 'Visitas@store');
    Route::delete('/v1/visitas/{cod_credencial}', 'Visitas@delete');

    //FERIADOS
    Route::get('/v1/feriados/lista/{export?}', 'Feriados@index');
    Route::get('/v1/feriados/gridOptions/{version?}', 'Feriados@gridOptions');
    Route::get('/v1/feriados/{clave}/{ou_sel?}', 'Feriados@detalle');
    Route::put('/v1/feriados', 'Feriados@update');
    Route::post('/v1/feriados', 'Feriados@store');
    Route::delete('/v1/feriados/{clave}', 'Feriados@delete');

    //SUCESOS
    Route::get('/v1/movisucesos/lista/{export?}', 'MoviSucesos@index');
    Route::get('/v1/movisucesos/gridOptions/{version?}', 'MoviSucesos@gridOptions');
    Route::get('/v1/movisucesos/{stm_evento}/{cod_ou?}', 'MoviSucesos@detalle');

    //EVENTOS
    Route::get('/v1/movieventos/lista/{export?}', 'MoviEventos@index');
    Route::get('/v1/movieventos/gridOptions/{version?}', 'MoviEventos@gridOptions');

    //MOVIMIENTOS POR SECTOR
    Route::get('/v1/movicredsectores/lista/{export?}', 'MoviCredSectores@index');
    Route::get('/v1/movicredsectores/gridOptions/{version?}', 'MoviCredSectores@gridOptions');
    Route::get('/v1/movicredsectores/{stm_evento}/{cod_ou?}', 'MoviCredSectores@detalle');
    Route::delete('/v1/movicredsectores/{cod_sector}', 'MoviCredSectores@delete');


    //LOGS
    Route::get('/v1/logs/combo', 'LogParser@getLogList');
    Route::get('/v1/logs/lista/{log_id}/{posicion?}', 'LogParser@index');

    //CLASES 
    Route::get('/v1/clases/combo/{export?}', 'Temas@getClases');


    //ESQUEMAS DE ACCESO
    Route::get('/v1/esquemas/lista/{export?}', 'Esquemas@index');
    Route::get('/v1/esquemas/gridOptions/{version?}', 'Esquemas@gridOptions');
    Route::get('/v1/esquemas/combo/{xusuario?}', 'Esquemas@getEsquemas');
    Route::get('/v1/esquemas/comboxou/{cod_ou?}/{xusuario?}', 'Esquemas@getEsquemasxOU');
    Route::get('/v1/esquemas/{clave}/{cod_ou_sel}', 'Esquemas@detalle');
    Route::put('/v1/esquemas', 'Esquemas@update');
    Route::post('/v1/esquemas', 'Esquemas@store');
    Route::delete('/v1/esquemas/{clave}', 'Esquemas@delete');

    //DASHBOARD
    Route::get('/v1/processor', 'ProcessorMetrics@getData');
    Route::get('/v1/io/val', 'IOAPI@getIOVal');
    Route::get('/v1/ioext/{id_disp_origen}/{io_name}', 'IOAPI@getIOExtVal');
    Route::get('/v1/ios', 'IOAPI@getData');
    Route::get('/v1/diskspace/{selected_disk?}', 'Diskspace@getData')->where('selected_disk', '(.*)');

    //CONFIGURACION GRUPO DE CREDENCIALES
    Route::get('/v1/confgrupocred/combo', 'GruposCredenciales@getGrupo');
    Route::get('/v1/confgrupocred/lista/{export?}', 'GruposCredenciales@index');
    Route::get('/v1/confgrupocred/gridOptions/{version?}', 'GruposCredenciales@gridOptions');
    Route::get('/v1/confgrupocred/{cod_grupo}/{cod_ou?}', 'GruposCredenciales@detalle');
    Route::put('/v1/confgrupocred', 'GruposCredenciales@update');
    Route::post('/v1/confgrupocred', 'GruposCredenciales@store');
    Route::delete('/v1/confgrupocred/{cod_grupo}', 'GruposCredenciales@delete');

    //CONFIGURACION SUCESOS
    Route::get('/v1/confsucesos/lista/{export?}', 'ConfSucesos@index');
    Route::get('/v1/confsucesos/gridOptions/{version?}', 'ConfSucesos@gridOptions');
    Route::get('/v1/confsucesos/{cod_suceso}/{cod_ou?}', 'ConfSucesos@detalle');
    Route::put('/v1/confsucesos', 'ConfSucesos@update');
    Route::post('/v1/confsucesos', 'ConfSucesos@store');
    Route::delete('/v1/confsucesos/{cod_suceso}', 'ConfSucesos@delete');

    //PARAMETROS DE CONFIGURACION
    Route::get('/v1/parametros/lista/{export?}', 'Parametros@index');
    Route::get('/v1/parametros/gridOptions/{version?}', 'Parametros@gridOptions');
    Route::get('/v1/parametros/demonios', 'Parametros@listDaemons');
    Route::get('/v1/parametros/{den_parametro}/{cod_ou?}', 'Parametros@detalle');
    Route::put('/v1/parametros', 'Parametros@update');
    Route::post('/v1/parametros/reiniciademonio', 'Parametros@restartDaemon');
    Route::post('/v1/parametros', 'Parametros@store');
    Route::post('/v1/sendmailtest', 'Parametros@sendMailTest');
    Route::post('/v1/sendchattest', 'Parametros@sendChatTest');
    Route::delete('/v1/parametros/{den_parametro}', 'Parametros@delete');

    //Display Sucesos
    Route::post('/v1/displaysucesos/resetear', 'DisplaySucesos@resetear');
    Route::post('/v1/displaysucesos/restaurarTema', 'MoviDisplayTemas@restaurarTema');


    /* MODULO ASIS */
    //FERIADOS
    Route::get('/v1/feriadosasis/lista/{export?}', 'Asis\FeriadosAsis@index');
    Route::get('/v1/feriadosasis/gridOptions/{version?}', 'Asis\FeriadosAsis@gridOptions');
    Route::get('/v1/feriadosasis/{cod_sector}/{ou_sel?}', 'Asis\FeriadosAsis@detalle');
    Route::put('/v1/feriadosasis', 'Asis\FeriadosAsis@update');
    Route::post('/v1/feriadosasis', 'Asis\FeriadosAsis@store');
    Route::post('/v1/feriadosasis/update', 'Asis\FeriadosAsis@updateFeriados');
    Route::delete('/v1/feriadosasis/{cod_sector}', 'Asis\FeriadosAsis@delete');

    //TIPO NOVEDAD
    Route::get('/v1/tiponovedad/lista/{export?}', 'Asis\TiposNovedades@index');
    Route::get('/v1/tiponovedad/gridOptions/{version?}', 'Asis\TiposNovedades@gridOptions');
    Route::get('/v1/tiponovedad/getTiposNovedades', 'Asis\TiposNovedades@getTiposNovedades');
    Route::get('/v1/tiponovedad/{tipo_novedad}/{ou_sel?}', 'Asis\TiposNovedades@detalle');
    Route::put('/v1/tiponovedad', 'Asis\TiposNovedades@update');
    Route::post('/v1/tiponovedad', 'Asis\TiposNovedades@store');
    Route::delete('/v1/tiponovedad/{tipo_novedad}', 'Asis\TiposNovedades@delete');

    //EMPLEADOS
    Route::get('/v1/empleados/lista/{export?}', 'Asis\Empleados@index');
    Route::get('/v1/empleados/gridOptions/{version?}', 'Asis\Empleados@gridOptions');
    Route::get('/v1/empleados/getEmpleados/{cod_empresa?}', 'Asis\Empleados@getEmpleados');
    Route::get('/v1/empleados/{cod_empleado}/{ou_sel?}', 'Asis\Empleados@detalle');
    Route::put('/v1/empleados', 'Asis\Empleados@update');
    Route::post('/v1/empleados', 'Asis\Empleados@store');
    Route::post('/v1/empleados/horarios', 'Asis\Empleados@storeHorarios');
    Route::delete('/v1/empleados/{cod_empleado}', 'Asis\Empleados@delete');

    //Organizaciones
    Route::get('/v1/empresas/lista/{export?}', 'Asis\Empresas@index');
    Route::get('/v1/empresas/gridOptions/{version?}', 'Asis\Empresas@gridOptions');
    Route::get('/v1/empresas/combo', 'Asis\Empresas@getEmpresas');
    Route::get('/v1/empresas/{cod_empresa}/{ou_sel?}', 'Asis\Empresas@detalle');
    Route::post('/v1/empresas', 'Asis\Empresas@updateEmpresas');

    // Route::put('/v1/empresas', 'Asis\Empresas@update');
    // Route::post('/v1/empresas', 'Asis\Empresas@store');
    // Route::delete('/v1/empresas/{cod_empresa}', 'Asis\Empresas@delete');

    //NOVEDADES
    Route::get('/v1/novedades/lista/{export?}', 'Asis\Novedades@index');
    Route::get('/v1/novedades/gridOptions/{version?}', 'Asis\Novedades@gridOptions');
    Route::get('/v1/novedades/{clave}/{ou_sel?}', 'Asis\Novedades@detalle');
    Route::put('/v1/novedades', 'Asis\Novedades@update');
    Route::post('/v1/novedades', 'Asis\Novedades@store');
    Route::delete('/v1/novedades/{clave}', 'Asis\Novedades@delete');

    //REGISTROS
    Route::get('/v1/registros/lista/{export?}', 'Asis\Registros@index');
    Route::get('/v1/registros/gridOptions/{version?}', 'Asis\Registros@gridOptions');
    Route::get('/v1/registros/{clave}/{ou_sel?}', 'Asis\Registros@detalle');
    Route::put('/v1/registros', 'Asis\Registros@update');
    Route::post('/v1/registros', 'Asis\Registros@store');
    Route::post('/v1/registros/empleados', 'Asis\Registros@updateEmpleados');
    Route::post('/v1/registros/horarios', 'Asis\Registros@updateHorarios');
    Route::post('/v1/registros/novedades', 'Asis\Registros@updateNovedades');
    Route::delete('/v1/registros/{clave}/{ou_sel?}', 'Asis\Registros@delete');
});
