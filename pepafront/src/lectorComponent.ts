'use strict';

const appLectorComponent = {
    template: require('./Pages/Templates/lector_template.html'),
    controllerAs: "lector",
    controller: LectorController,
    bindings: {
        json_parametros: '=ngModel',
        readonly: '<'
    }
};

LectorController.$inject = ['$scope', 'localData'];
function LectorController($scope, localData) {

    const vm = this;
    vm.tipoCredencial = localData.getTipoCredencial();
    vm.indMovimiento = localData.getIndMovimiento();
    vm.indRetencion = localData.getIndRetencion();
    vm.habilitacionesList = localData.getTipoHabilitacion();
}

export default appLectorComponent;
