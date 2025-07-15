'use strict';
import angular from "angular";

const appTipoUsoComponent = {
//    selector:"app-tipo-uso",
    template: require('../Pages/Templates/tipo_uso_template.html'),
    controllerAs: "tipouso",
    controller: TipoUsoController,
    bindings: {
        codTipoUso: '<',
        json_parametros: '=ngModel',
        readonly: '<'
    },
};
TipoUsoController.$inject = ['$scope', '$element'];
function TipoUsoController($scope, $element) {
    console.log('TipoUsoController');

    const vm = this;
    vm.json_parametros = {};

    this.$onChanges = (changes) => {
        console.log('cambio', changes);
        if (changes.codTipoUso.previousValue) {
            vm.json_parametros = {};
        }

        angular.forEach($element.find('input'), function ($el) {
            angular.element($el).attr('readonly', vm.readonly);
            angular.element($el).attr('disabled', vm.readonly);
        });
        angular.forEach($element.find('textarea'), function ($el) {
            angular.element($el).attr('readonly', vm.readonly);
        });
        angular.forEach($element.find('select'), function ($el) {
            angular.element($el).attr('disabled', vm.readonly);
        });
//        angular.element('#adjunta_imagen1').prop('readonly', false);
//        angular.element('#adjunta_imagen1').prop('disabled', false);
    }
}


export default appTipoUsoComponent;
