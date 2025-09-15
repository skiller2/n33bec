'use strict';

const appDoutComponent = {
    template: require('./Pages/Templates/dout_template.html'),
    controllerAs: "dout",
    controller: DOUTController,
    bindings: {
        json_parametros: '=ngModel'
    }
};

DOUTController.$inject = ['$scope', 'localData'];
function DOUTController($scope, localData) {
    const vm = this;
    vm.coloresList = localData.getColores();
    //        vm.botonesList = localData.getBotones();
}
export default appDoutComponent;
