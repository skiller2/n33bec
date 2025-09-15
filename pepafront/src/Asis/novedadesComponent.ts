'use strict';
const novedadesComponent =
{
  template: require('.././Pages/Asis/novedades.html'),
  bindings: {
  },
  controllerAs: "novedades",
  controller: ['localData', 'datosBack', '$state', '$timeout','$translate',
    function (localData, datosBack, $state, $timeout,$translate) {
      const vm = this;
      vm.form_title = 'Novedades';
      vm.novedad = {};
      vm.novedad_dt = {};
      
      vm.action_title = '';
      vm.action = '';
      
      
      vm.hide = true;
      vm.active = 0;
      vm.empleadosList = [];
      vm.novedad.empleadosSel = [];
      vm.noteditable = false;

      datosBack.getData('tiponovedad/getTiposNovedades', false, false).then(function (resultado) {
        vm.tiposNovedades = resultado;
      });

      localData.getListaOU(false).then(function (resultado) {
        vm.ouList = resultado;
      }).catch(function () { });

      vm.getEmpleados = function () {
        datosBack.getData('empleados/getEmpleados/' + vm.novedad.cod_empresa, false, false).then(function (resultado) {
          vm.empleadosList = resultado;
          vm.novedad.empleadosSel = [];
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
        vm.novedad = {};
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
              datosBack.detalle('novedades', dtkey)
                .then(function (response) {
                  vm.novedad = response.data;
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
        vm.novedad_dt = {};
        if (dtkey !== '') {
          datosBack.detalle('novedades', dtkey)
            .then(function (response) {
              vm.novedad_dt = response.data;
            })
            .catch(function () { });
        }
      };

      vm.ok = function () {
        return datosBack.save(vm.action, 'novedades', vm.novedad, vm.grilla.getLastSelected())
          .then(function () {
            vm.grilla.fillGrid();
            vm.novedad = {};
            vm.active = 0;
            vm.hide = true;
            $state.go('.', { action: 'lista' });
          })
          .catch(function () { });
      };

      // delete record
      vm.confirmDelete = function () {
        datosBack.delete('novedades', vm.grilla.getLastSelected()).then(function (response) {
          vm.grilla.fillGrid();

        }).catch(function (data) { });
      };

      vm.exportar = function (tipo) {
        datosBack.export(vm.grilla.getLoadOptions(), tipo).then(function (response) { }).catch(function (data) { });
      };

    },
  ]
};

export default novedadesComponent;