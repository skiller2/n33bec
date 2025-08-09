'use strict';
const empleadosComponent =
{
  template: require('../../Pages/Asis/empleados.html'),
  bindings: {
  },
  controllerAs: "empleados",
  controller: ['localData', 'datosBack', '$state', '$timeout','$translate',
    function (localData, datosBack, $state,$timeout,$translate) {
      const vm = this;
      vm.form_title = 'Empleados';
      vm.empleado = {};
      vm.empleado2 = {};
      vm.empleado_dt = {};
      
      vm.action_title = '';
      
      vm.hide = true;
      vm.active = 0;
      
      vm.action = '';
      vm.tipoDocumento = localData.getTipoDocumento();
      vm.tipoSexo = localData.getTipoSexo();
      vm.checked = true;

      vm.uiOnParamsChanged = function (newParams) {
        switch (newParams.action) {
          case 'lista':
            vm.active = 0;
            break;
          case 'detalle':
            vm.active = 1;
            vm.consulta();
            break;
          case 'edita':
            vm.active = 2;
            vm.toggle(newParams.action);
            break;
          case 'agrega':
            vm.active = 2;
            vm.toggle(newParams.action);
            break;
          case 'copia':
            vm.active = 2;
            vm.toggle(newParams.action);
            break;
          case 'horarios':
            vm.active = 3;
            vm.empleado2 = {};
            break;
          default:
            $state.go('.', {
              action: 'lista',
            });
            break;
        }
      };

      vm.$onInit = function () {
        if ($state.params.action === 'lista' || $state.params.action === 'agrega') {
          vm.uiOnParamsChanged($state.params);
        } else {
          $state.go('.', {
            action: 'lista',
          });
        }
        $timeout(function () { vm.grilla.fillGrid() },0);
      };

      localData.getListaEmpresas().then(function (resultado) {
        vm.ouList = resultado;
      }).catch(function () { });

      vm.getEmpleados = function () {
        datosBack.getData('empleados/getEmpleados/' + vm.empleado2.cod_empresa, false, false).then(function (resultado) {
          vm.empleadosList = resultado;
          vm.empleado2.empleadosSel = [];
        }).catch(function () { });
      };

      vm.gridOptions = {
        data: [],
        enableFullRowSelection: true,
        enablePaginationControls: false,
        enableRowHeaderSelection: true,
        enableRowSelection: true,
        excludeProperties: '__metadata',
        multiSelect: false,
        useExternalSorting: true,
      };

      vm.toggle = function (action) {
        vm.empleado = {};
        vm.action = action;
        const dtkey = vm.grilla.getdtKey();

        switch (action) {
          case 'agrega':
            vm.action_title =$translate.instant('Alta');
            
            vm.noteditable = false;
            vm.hide = false;
            vm.active = 2;
            vm.checked = true;
            vm.empleado.ind_activo = true;
            break;
          case 'copia':
            if (dtkey && dtkey.length > 0) {
              vm.action_title =$translate.instant('Alta');;
              vm.noteditable = false;
              datosBack.detalle('empleados', dtkey)
                .then(function (response) {
                  vm.empleado = response.data;
                  vm.checked = vm.empleado.ind_activo;
                  vm.empleado.cod_empleado = '';
                  vm.active = 2;
                  vm.hide = false;
                })
                .catch(function () { });
            }
            break;
          case 'edita':
            if (dtkey && dtkey.length > 0) {
              vm.action_title = $translate.instant('Modificación');
              vm.noteditable = true;
              datosBack.detalle('empleados', dtkey)
                .then(function (response) {
                  vm.empleado = response.data;
                  vm.checked = vm.empleado.ind_activo;
                  vm.active = 2;
                  vm.hide = false;
                })
                .catch(function () { });
            }
            break;
          default:
            break;
        }
      };

      vm.consulta = function () {
        const dtkey = vm.grilla.getdtKey();
        vm.empleado_dt = {};
        if (dtkey !== '') {
          datosBack.detalle('empleados', dtkey)
            .then(function (response) {
              vm.empleado_dt = response.data;
            })
            .catch(function () { });
        }
      };

      vm.ok = function () {
        return datosBack.save(vm.action, 'empleados', vm.empleado, vm.grilla.getLastSelected())
          .then(function () {
            vm.grilla.fillGrid();
            vm.empleado = {};
            // localData.resetListaOU();
            vm.active = 0;
            vm.hide = true;
            $state.go('.', { action: 'lista' });
          })
          .catch(function () { });
      };

      vm.ok2 = function () {
        return datosBack.save('update', 'empleados/horarios', vm.empleado2, vm.grilla.getLastSelected())
          .then(function () {
            vm.grilla.fillGrid();
            vm.empleado2 = {};
            vm.active = 0;
            vm.hide = true;
            $state.go('.', { action: 'lista' });
          })
          .catch(function () { });
      };

      vm.getDatosPersona = function (persona) {
        if (persona !== '' && persona.length > 2) {
          return datosBack.getData('usuarios/getPersona/' + persona, false, false).then(function (resultado) {
            return resultado;
          });
        }
      };

      // delete record
      vm.confirmDelete = function () {
        datosBack.delete('empleados', vm.grilla.getLastSelected()).then(function (response) {
          vm.grilla.fillGrid();
          // localData.resetListaOU();

        }).catch(function (data) { });
      };

      vm.typeaheadOnSelect = function ($item, $model, $label) {
        vm.empleado.cod_persona = $model.cod_persona || '';
        if (vm.empleado.cod_persona !== '') {
          vm.fbuscaPersona();
        }
        vm.empleado.busq_persona = '';
      };

      vm.exportar = function (tipo) {
        datosBack.export(vm.grilla.getLoadOptions(), tipo).then(function (response) { }).catch(function (data) { });
      };

      // Valida Persona, si existe, devuelve los datos
      vm.fbuscaPersona = function () {
        let model = vm.empleado;

        if (model.cod_persona === 'DNI' || model.cod_persona === 'PAS') {
          model.ape_persona = '';
          model.cod_sexo = '';
          model.cod_tipo_doc = model.cod_persona;
          model.nro_documento = model.bus_persona_tmp;
          model.nro_documento_ant = model.bus_persona_tmp;
          model.nom_persona = '';
          model.email = '';
          model.img_persona = '';
          model.cod_persona = '';
          model.bus_persona_tmp = '';
          return;
        }

        if (model.cod_persona === '') {
          return;
        }
        // if (model.nro_documento === vm.lastdni)
        //  return;
        datosBack.detalle('habilitaciones/valida/cod_persona', [model.cod_persona, 'P'])
          .then(function (response) {

            model.ape_persona = response.data.datosPersona.ape_persona;
            model.cod_sexo = response.data.datosPersona.cod_sexo;
            model.cod_tipo_doc = response.data.datosPersona.cod_tipo_doc;
            model.nro_documento = response.data.datosPersona.nro_documento;
            model.nom_persona = response.data.datosPersona.nom_persona;
            model.email = response.data.datosPersona.email;
            // model.img_persona = response.data.datosPersona.img_persona;
            model.nro_documento_ant = response.data.datosPersona.nro_documento;

            /*datosBack.detalle('imagenes', [
                [model.cod_persona],
              ])
              .then(function(response) {
                model.img_persona = response.data.img_persona;
              })
              .catch(function() {});*/
          })
          .catch(function () { });
      };

    },
  ]
};

export default empleadosComponent;        
