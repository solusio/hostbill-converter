<?php

class FileService
{
    public const SERVERS = 'servers';
    public const IMPORTED_SERVERS = 'imported_servers';
    public const IMPORTED_LOCATIONS = 'imported_locations';
    public const COMPUTE_RESOURCES = 'compute_resources';
    public const OS_IMAGES = 'os_images';
    public const PLANS = 'plans';
    public const LOCATIONS = 'locations';
    public const ROLES = 'roles';

    public function load(string $file): array
    {
        $data = file_get_contents(__DIR__ . "/../../../resources/$file.json");

        if (!$data) {
            throw new \Exception("File $file.json is not found. Run `prepare` command to init necessary files.");
        }

        return json_decode($data, true);
    }

    public function save(string $file, array $data): void
    {
        file_put_contents(__DIR__ . "/../../../resources/$file.json", json_encode($data));
    }
}