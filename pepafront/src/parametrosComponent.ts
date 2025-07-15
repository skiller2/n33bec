'use strict';
const parametrosComponent =
{
  template: require('../Pages/parametros.html'),
  bindings: {
  },
  controllerAs: "parametros",
  controller: ['$timeout', 'datosBack', '$state','$translate',
    function ($timeout, datosBack, $state,$translate) {

      let vm = this;
      vm.form_title = $translate.instant('Parámetros de configuración');
      vm.parametro = {};
      vm.parametro_dt = {};
      vm.action_title = '';
      
      
      vm.hide = true;
      vm.active = 0;
      vm.mailtest = {};
      
      vm.selected_daemon = { cod_daemon: "" };

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
          case 'mail':
            vm.active = 3;
            break;
          case 'mensajeria':
              vm.active = 5;
              break;
  
          case 'procesos':
            vm.active = 4;
            vm.listademonios = [];
            datosBack.getData('parametros/demonios')
              .then(function (response) {
                console.log('procesos', response);
                vm.listademonios = response;
                //                vm.parametro_dt = response.data;
              })
              .catch(function () { });

            break;
  
          default:
            $state.go('.', {
              action: 'lista',
            });
            break;
        }
      };

      vm.$onInit = function () {
        if ($state.params.action === 'lista' || $state.params.action === 'agrega' || $state.params.action === 'procesos') {
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
        vm.parametro = {};
        vm.action = action;
        const dtkey = vm.grilla.getdtKey();

        switch (action) {
          case 'agrega':
            vm.action_title = $translate.instant('Alta');
            
            vm.noteditable = false;
            vm.hide = false;
            vm.active = 2;
            break;
          case 'copia':
            if (dtkey && dtkey.length > 0) {
              vm.action_title = $translate.instant('Alta');
              vm.noteditable = false;
              datosBack.detalle('parametros', dtkey)
                .then(function (response) {
                  vm.parametro = response.data;
                  vm.parametro.den_parametro = '';
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
              datosBack.detalle('parametros', dtkey)
                .then(function (response) {
                  vm.parametro = response.data;
                  vm.active = 2;
                  vm.hide = false;
                })
                .catch(function () { });
            }
            break;
          case 'procesos':
                
            break;
          default:
            break;
        }
      };

      vm.consulta = function () {
        const dtkey = vm.grilla.getdtKey();
        vm.parametro_dt = {};
        if (dtkey !== '') {
          datosBack.detalle('parametros', dtkey)
            .then(function (response) {
              vm.parametro_dt = response.data;
            })
            .catch(function () { });
        }
      };

      vm.ok = function () {
        return datosBack.save(vm.action, 'parametros', vm.parametro, vm.grilla.getLastSelected())
          .then(function () {
            vm.grilla.fillGrid();
            vm.parametro = {};
            vm.active = 0;
            vm.hide = true;
            $state.go('.', { action: 'lista' });
          })
          .catch(function () { });
      };

      vm.sendmailtest = function () {
        return datosBack.save('mail', 'sendmailtest', vm.mailtest, '')
          .then(function () {
            // vm.grilla.fillGrid();
            // vm.parametro = {};
            $state.go('.', { action: 'lista' });
          })
          .catch(function () { });
      };
      vm.sendchattest = function () {
        return datosBack.save('chat', 'sendchattest', vm.chattest, '')
          .then(function () {
            // vm.grilla.fillGrid();
            // vm.parametro = {};
            //$state.go('.', { action: 'lista' });
          })
          .catch(function () { });
      };

      vm.resetDaemon = function () {
        return datosBack.save('proceso', 'parametros/reiniciademonio/', { "cod_daemon": vm.selected_daemon.cod_daemon }, '')
          .then(function () {
            // vm.grilla.fillGrid();
            // vm.parametro = {};
            //              $state.go('.', {action: 'lista'});
          })
          .catch(function () { });
      };
      vm.procesoUpdate = function () {
        //          vm.cod_daemon = 

      };



      // delete record
      vm.confirmDelete = function () {
        datosBack.delete('parametros', vm.grilla.getLastSelected()).then(function (response) {
          vm.grilla.fillGrid();
        }).catch(function (data) { });
      };


    },
  ]
};

export default parametrosComponent;