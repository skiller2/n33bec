'use strict';

const appAoutComponent = {
    template: require('./Pages/Templates/aout_template.html'),
    controllerAs: "aout",
    controller: AOUTController,
    bindings: {
        json_parametros: '=ngModel'
    }
};

AOUTController.$inject = ['$scope', 'localData'];
function AOUTController($scope, localData) {
    const vm = this;
}
export default appAoutComponent;
