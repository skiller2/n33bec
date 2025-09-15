'use strict';

const appDinComponent = {
    template: require('./Pages/Templates/din_template.html'),
    controllerAs: "din",
    controller: DINController,
    bindings: {
        json_parametros: '=ngModel'
    }
};

DINController.$inject = ['$scope', 'localData'];
function DINController($scope, localData) {
    const vm = this;
    vm.coloresList = localData.getColores();
    //        vm.botonesList = localData.getBotones();
}
export default appDinComponent;
