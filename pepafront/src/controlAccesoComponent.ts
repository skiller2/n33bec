'use strict';

const controlAccesoComponent =
{
    template: require('./Pages/Templates/control_acceso.html'),
    bindings: {
        onClose: '&'
    },
    controllerAs: "ctrl",
    controller: ['$element', '$scope', '$window', '$timeout', 'datosBack', '$attrs', '$sce', '$uibModal', function ($element, $scope, $window, $timeout, datosBack, $attrs, $sce, $uibModal) {
        const vm = this;
        
        
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

        vm.$onInit = function () {
            $timeout(function () { vm.grilla.fillGrid() },0);
        };

        vm.$onDestroy = function () {
        }

        vm.close = () => {
            if ($attrs.onClose) {
                vm.onClose();
                //                    vm.$onDestroy();
            }
        }

        vm.exportar = function (tipo) {
            datosBack.export(vm.grilla.getLoadOptions(), tipo).then(function (response) { }).catch(function (data) { });
        };
    }],
};

export default controlAccesoComponent;