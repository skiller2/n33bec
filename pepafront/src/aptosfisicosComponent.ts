'use strict';
import angular from 'angular';


const aptosfisicosComponent = {
    template: require('../Pages/aptosfisicos.html'),
    bindings: {},
    controllerAs: "aptosfisicos",
    controller: ['localData', 'datosBack', '$state','$timeout','$translate',
      function (localData, datosBack, $state, $timeout,$translate) {

        const vm = this;
        vm.aptofisico = {};
        vm.aptofisico_dt = {};
        vm.action_title = '';
        
        
        vm.hide = true;
        vm.copy = false;
        vm.active = 0;
        
        vm.tipoSexo = localData.getTipoSexo();
        vm.tipoDocumento = localData.getTipoDocumento();
        vm.form_title = $translate.instant('Aptos Físicos');
        vm.vacredenciales = [];

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
            default:
              $state.go('.', {
                action: 'lista',
              });
              break;
          }
        };

        vm.$onInit = function () {
          if ($state.params.action === 'lista') {
            vm.uiOnParamsChanged($state.params);
          } else {
            $state.go('.', {
              action: 'lista',
            });
          }
          $timeout(function () { vm.grilla.fillGrid() }, 0);
        };

        const mystate = $state.$current;

        // hack para llamar a $onDestroy
        mystate.onExit = function () {
          vm.$onDestroy();
        };

        this.$onDestroy = function () {
        };

        // Set search items for starting and reset.
        vm.gridOptions = {
          excludeProperties: '__metadata',
          enablePaginationControls: false,
          enableRowSelection: true,
          enableRowHeaderSelection: true,
          multiSelect: false,
          enableFullRowSelection: true,
          useExternalSorting: true,
          data: [],
          // multiSelect: false
        };

        // show modal form
        vm.toggle = function (action) {
          vm.aptofisico = {};
          vm.action = action;
          const dtkey = vm.grilla.getdtKey();

          switch (action) {
            case 'edita':
              if (dtkey && dtkey.length > 0) {
                vm.action_title = $translate.instant('Modificación');
                datosBack.detalle('personas', dtkey, false)
                  .then(function (response) {
                    vm.aptofisico = response.data.pers;
                    vm.aptofisico.ind_bloqueo = (response.data.pers.ind_bloqueo === '1') ? true : false;
                    vm.active = 2;
                    vm.hide = false;
                    if (response.data.datosCred.length > 1) {
                      vm.aptofisico.vacredenciales = response.data.datosCred;
                    }
                  })
                  .catch(function () { });

                datosBack.detalle('imagenes', dtkey, false)
                  .then(function (response) {
                    vm.aptofisico.img_persona = response.data.img_persona;
                  })
                  .catch(function () { });

                datosBack.detalle('aptosfisicos', dtkey, false)
                  .then(function (response) {
                    vm.aptofisico.img_apto_fisico = response.data.img_apto_fisico;
                    vm.aptofisico.fec_otorgamiento_af = response.data.fec_otorgamiento_af;
                    vm.aptofisico.fec_vencimiento_af = response.data.fec_vencimiento_af;
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
          vm.aptofisico_dt = {};
          if (dtkey !== '') {
            datosBack.detalle('personas', dtkey, false)
              .then(function (response) {
                vm.aptofisico_dt = response.data.pers;
                vm.aptofisico_dt.ind_bloqueo = (response.data.pers.ind_bloqueo === '1') ? true : false;
                if (response.data.datosCred.length > 1) {
                  vm.aptofisico_dt.vacredenciales = response.data.datosCred;
                }
              })
              .catch(function () { });

            datosBack.detalle('imagenes', dtkey, false)
              .then(function (response) {
                vm.aptofisico_dt.img_persona = response.data.img_persona;
              })
              .catch(function () { });
              
            datosBack.detalle('aptosfisicos', dtkey, false)
              .then(function (response) {
                vm.aptofisico_dt.img_apto_fisico = response.data.img_apto_fisico;
                vm.aptofisico_dt.fec_otorgamiento_af = response.data.fec_otorgamiento_af;
                vm.aptofisico_dt.fec_vencimiento_af = response.data.fec_vencimiento_af;
              })
              .catch(function () { });
          }
        };

        vm.ok = function () {
          return datosBack.save(vm.action, 'aptosfisicos', vm.aptofisico, vm.grilla.getLastSelected()).then(function () {
            vm.grilla.fillGrid();
            vm.aptofisico = {};
            vm.active = 0;
            vm.hide = true;
            $state.go('.', { action: 'lista' });
          })
            .catch(function () { });
        };

        // delete record
        vm.confirmDelete = function () {
          datosBack.delete('aptosfisicos', vm.grilla.getLastSelected()).then(function (response) {
            vm.grilla.fillGrid();
          }).catch(function (data) { });
        };
      },
    ]
};

export default aptosfisicosComponent;