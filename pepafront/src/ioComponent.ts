'use strict';
const ioComponent =
{
    template: require('../Pages/io.html'),
    bindings: {
    },
    controllerAs: "io",
    controller: ['$scope', 'store', 'datosBackIO', function ($scope, store, datosBackIO) {
        var vm = this;
        vm.name = 'ioDashboard';
        vm.collapsible = false;
        vm.maximizable = false;
        vm.categories = false;
        vm.tiempo_recarga_seg = "10";
        vm.model = {
            title: "I/O",
            structure: "4-4-4"
        };

        if (store.get('ioDashboardModel')) {
            vm.model = store.get('ioDashboardModel');
        }

        $scope.$on('$destroy', function () {
            datosBackIO.stop();
        });

        $scope.$on('adfDashboardChanged', function (event, name, model) {
            store.set('ioDashboardModel', vm.model);
        });
    }]
};

export default ioComponent;