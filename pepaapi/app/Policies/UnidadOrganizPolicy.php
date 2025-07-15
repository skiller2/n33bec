<?php

namespace App\Policies;

use App\Usuario;
use App\UnidadesOrganiz;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnidadOrganizPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the unidadOrganiz.
     *
     * @param  \App\User  $user
     * @param  \App\UnidadOrganiz  $unidadOrganiz
     * @return mixed
     */
    public function view(Usuario $user)
    {
        //
        return true;
    }

    /**
     * Determine whether the user can create unidadOrganizs.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(Usuario $user)
    {

        return isset($user['obj_permisos']['store-ou']);
    }

    /**
     * Determine whether the user can update the unidadOrganiz.
     *
     * @param  \App\User  $user
     * @param  \App\UnidadOrganiz  $unidadOrganiz
     * @return mixed
     */
    public function update(Usuario $user, UnidadesOrganiz $unidadOrganiz)
    {

        //
    }

    /**
     * Determine whether the user can delete the unidadOrganiz.
     *
     * @param  \App\User  $user
     * @param  \App\UnidadOrganiz  $unidadOrganiz
     * @return mixed
     */
    public function delete(Usuario $user, UnidadesOrganiz $unidadOrganiz)
    {

        //
    }
}
