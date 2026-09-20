<?php

class Role extends Model
{
    protected string $table = 'roles';

    public function findByName(string $nama): ?array
    {
        return $this->whereFirst('nama', $nama);
    }
}
