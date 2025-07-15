'use strict';

const appAinComponent = {
    template: require('../Pages/Templates/ain_template.html'),
    controllerAs: "ain",
    controller: AINController,
    bindings: {
        json_parametros: '=ngModel'
    }
};

AINController.$inject = ['$scope', 'localData'];
function AINController($scope, localData) {
    const vm = this;
    vm.coloresList = localData.getColores();
}
export default appAinComponent;

