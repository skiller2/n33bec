'use strict';

const appComunicComponent = {
    template: require('./Pages/Templates/comunic_template.html'),
    controllerAs: "comunic",
    controller: ComunicController,
    bindings: {
        json_parametros: '=ngModel'
    }
};

ComunicController.$inject = ['$scope', 'localData'];
function ComunicController($scope, localData) {
    const vm = this;
    vm.coloresList = localData.getColores();
    //        vm.botonesList = localData.getBotones();
}
export default appComunicComponent;


