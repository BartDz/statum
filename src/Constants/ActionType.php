<?php

final class ActionType
{
    public const LOGIN            = 'login';
    public const LOGOUT           = 'logout';
    public const ADD_SERVICE      = 'add-service';
    public const DELETE_SERVICE   = 'delete-service';
    public const ADD_INCIDENT     = 'add-incident';
    public const RESOLVE_INCIDENT = 'resolve-incident';

    private function __construct() {}
}
