'use strict';
class broadcastService {
    static $inject = ['$rootScope'];

    constructor(private $rs: ng.IRootScopeService) {
    }
    
    send = (channel: string, message: any) => {
        return this.$rs.$broadcast(channel, message)
    }

    on = (channel: string) => { 

    }
}

export default broadcastService;