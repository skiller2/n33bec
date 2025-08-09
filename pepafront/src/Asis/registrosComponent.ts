'use strict';
const registrosComponent =
{
  template: require('../../Pages/Asis/registros.html'),
  bindings: {
  },
  controllerAs: "registros",
  controller: ['localData', 'datosBack', '$state', '$timeout','$translate',
    function (localData, datosBack, $state, $timeout, $translate) {
      const vm = this;
      vm.form_title = 'Registros';
      vm.registro = {};
      vm.registro_dt = {};
      vm.update_reg = {};
      
      vm.action_title = '';
      vm.action = '';
      
      
      vm.hide = true;
      vm.active = 0;
      vm.empleadosList = [];
      vm.registro.empleadosSel = [];
      vm.noteditable = false;
      vm.readonlytn = true;
      vm.readonlytnt = true;
      vm.procesos = [{ id: 'empleados', name: 'Empleados' }, { id: 'horarios', name: 'Control de Acceso' }, { id: 'novedades', name: 'Novedades' }];

      datosBack.getData('tiponovedad/getTiposNovedades', false, false).then(function (resultado) {
        vm.tiposNovedades = resultado;
      });

      localData.getListaEmpresas().then(function (resultado) {
        vm.ouList = resultado;
      }).catch(function () { });

      vm.getEmpleados = function (ind_dt = false, ind_limpiar = true) {
        let cod_empresa = vm.registro.cod_empresa;
        if (ind_dt) {
          cod_empresa = vm.registro_dt.cod_empresa;
        }
        datosBack.getData('empleados/getEmpleados/' + cod_empresa, false, false).then(function (resultado) {
          vm.empleadosList = resultado;
          if (ind_limpiar) {
            vm.registro.empleadosSel = [];
          }
        }).catch(function () { });
      };

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
          case 'update_reg':
            vm.active = 3;
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

      // Set search items for starting and reset.
      vm.gridOptions = {
        data: [],
        enableFullRowSelection: true,
        enablePaginationControls: false,
        enableRowHeaderSelection: true,
        enableRowSelection: true,
        excludeProperties: '__metadata',
        multiSelect: false,
        useExternalSorting: true,
        // multiSelect: false
      };

      // show modal form
      vm.toggle = function (action) {
        vm.registro = {};
        vm.action = action;
        const dtkey = vm.grilla.getdtKey();

        switch (action) {
          case 'agrega':
            vm.action_title = $translate.instant('Alta');;
            
            vm.noteditable = false;
            vm.hide = false;
            // vm.active=2;
            break;
          case 'edita':
            if (dtkey && dtkey.length > 0) {
              vm.action_title = $translate.instant('Modificación');
              vm.noteditable = true;
              datosBack.detalle('registros', dtkey)
                .then(function (response) {
                  vm.registro = response.data;
                  vm.registro.ind_feriado = (response.data.ind_feriado === '1') ? true : false;
                  vm.registro.ind_dia_laborable = (response.data.ind_dia_laborable === '1') ? true : false;
                  vm.registro.ind_modif_novedad = (response.data.ind_modif_novedad === '1') ? true : false;
                  vm.registro.ind_modif_horarios = (response.data.ind_modif_horarios === '1') ? true : false;
                  vm.registro.ind_tarde = (response.data.ind_tarde === '1') ? true : false;


                  vm.getEmpleados(true, false);
                  vm.registro.empleadosSel = response.data.empleadosSel;
                  vm.hide = false;
                  vm.fchangeTipoNovedad();
                  vm.fchangeTipoNovedadTrabajo();
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
        vm.registro_dt = {};
        if (dtkey !== '') {
          datosBack.detalle('registros', dtkey)
            .then(function (response) {
              vm.registro_dt = response.data;
              vm.getEmpleados(true, false);
              vm.registro_dt.empleadosSel = response.data.empleadosSel;
              vm.registro_dt.ind_feriado = (response.data.ind_feriado === '1') ? true : false;
              vm.registro_dt.ind_dia_laborable = (response.data.ind_dia_laborable === '1') ? true : false;
              vm.registro_dt.ind_modif_novedad = (response.data.ind_modif_novedad === '1') ? true : false;
              vm.registro_dt.ind_modif_horarios = (response.data.ind_modif_horarios === '1') ? true : false;
              vm.registro_dt.ind_tarde = (response.data.ind_tarde === '1') ? true : false;
            })
            .catch(function () { });
        }
      };

      vm.ok = function () {
        return datosBack.save(vm.action, 'registros', vm.registro, vm.grilla.getLastSelected())
          .then(function () {
            vm.grilla.fillGrid();
            vm.registro = {};
            vm.active = 0;
            vm.hide = true;
            $state.go('.', { action: 'lista' });
          })
          .catch(function () { });
      };

      // delete record
      vm.confirmDelete = function () {
        datosBack.delete('registros', vm.grilla.getLastSelected()).then(function (response) {
          vm.grilla.fillGrid();
        }).catch(function (data) { });
      };

      vm.exportar = function (tipo) {
        datosBack.export(vm.grilla.getLoadOptions(), tipo).then(function (response) { }).catch(function (data) { });
      };

      vm.updateRegistros = function () {

        return datosBack.save('update', 'registros/' + vm.registro.proceso, vm.update_reg, '')
          .then(function () {
            // vm.update_reg = {};
            vm.grilla.setFilter({ pAvailableFrom: vm.update_reg.fec_desde, pAvailableTo: vm.update_reg.fec_hasta });
            vm.grilla.fillGrid();
            vm.active = 0;
            vm.hide = true;
            $state.go('.', { action: 'lista' });
          })
          .catch(function () { });
      };

      vm.fchangeTipoNovedad = function () {
        if (!vm.registro.tipo_novedad) {
          vm.registro.des_novedad = '';
          vm.readonlytn = true;
        } else {
          vm.readonlytn = false;
        }
      }

      vm.fchangeTipoNovedadTrabajo = function () {
        if (!vm.registro.tipo_novedad_trabajo) {
          vm.registro.des_novedad_trabajo = '';
          vm.readonlytnt = true;
        } else {
          vm.readonlytnt = false;
        }
      }
    },
  ]
};

export default registrosComponent;    