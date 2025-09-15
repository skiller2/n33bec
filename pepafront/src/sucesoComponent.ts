'use strict';

const appSucesoComponent = {
    template: require('./Pages/Templates/suceso_template.html'),
    controllerAs: "suceso",
    controller: SucesoController,
    bindings: {
        json_parametros: '=ngModel',
        imagenes: '=',
        readonly: '<'
    }
};

SucesoController.$inject = ['$scope', 'localData'];
function SucesoController($scope, localData) {
    const vm = this;
}
export default appSucesoComponent;

