'use strict';

const appDinExtComponent = {
    template: require('./Pages/Templates/din_ext_template.html'),
    controllerAs: "dinext",
    controller: DINEXTController,
    bindings: {
        json_parametros: '=ngModel'
    }
};

DINEXTController.$inject = ['$scope', 'localData'];
function DINEXTController($scope, localData) {
    const vm = this;
    vm.coloresList = localData.getColores();
    //        vm.botonesList = localData.getBotones();
}
export default appDinExtComponent;

