<?php

class ApiV2Service
{
    public int $module;
    public int $serverId;
    private DatabaseService $db;
    private Hosting\SolusIO\Api $api;

    public const KVM = 'kvm';
    public const VZ = 'vz';

    public function __construct(DatabaseService $db)
    {
        $this->db = $db;
    }

    public function init(): void
    {
        $this->module = $this->db->getModule(DatabaseService::SolusVMv2);

        $server = $this->db->getServerToApiConnection($this->module);

        $connection = $this->db->getConnection($server);
        if (empty($connection)) {
            throw new Exception('No v2 API connection');
        }
        $this->serverId = (int)$connection['id'];

        $this->api = new Hosting\SolusIO\Api(
            !empty($connection['ip']) ? $connection['ip'] : $connection['host'],
            $connection['hash'],
        );
    }

    public function getSolusVm2Servers(): array
    {
        return $this->request('servers', function (array &$data, array $item): void {
            $data[$item['id']] = [
                'name' => $item['name'],
                'user_id' => $item['user']['id'],
            ];
        });
    }

    public function getRoles(): array
    {
        return $this->request('roles', function (array &$data, array $item): void {
            $data[$item['name']] = $item['id'];
        });
    }

    public function getLocations(): array
    {
        return $this->request('locations', function (array &$data, array $item): void {
            $data[$item['name']] = [
                'id' => $item['id'],
                'is_visible' => $item['is_visible'],
            ];
        });
    }

    public function getPlans(): array
    {
        return $this->request('plans', function (array &$data, array $item): void {
            $data[$item['virtualization_type']][$item['name']] = [
                'id' => $item['id'],
                'is_visible' => $item['is_visible'],
            ];
        });
    }

    public function getOsImages(): array
    {
        return $this->request('os_images', function (array &$data, array $item): void {
            foreach ($item['versions'] as $version) {
                $data[$version['virtualization_type']][$version['url']] = [
                    'id' => $version['id'],
                    'name' => $item['name'] . ' ' . $version['version'],
                    'is_visible' => $item['is_visible'],
                ];
            }
        });
    }

    public function getComputeResourceLocations(): array
    {
        return $this->request('compute_resources', function (array &$data, array $item): void {
            foreach ($item['locations'] as $location) {
                if ($location['is_visible']) {
                    $data[$item['name']] = $location['id'];
                    break;
                }
            }
        });
    }

    public function getClusterImports(): array
    {
        return $this->request('cluster_imports');
    }

    public function getClusterImportEntities(int $clusterImport, string $destination): array
    {
        $path = "cluster_imports/$clusterImport/entities/$destination";
        return $this->request($path, function (array &$data, array $item): void {
            $data[$item['source_id']] = $item['destination_id'];
        });
    }

    private function request(string $path, ?callable $callback = null): array
    {
        $page = 1;
        $data = [];
        while (true) {
            $response = $this->api->request($path, 'GET', ['page' => $page]);
            if ($callback !== null) {
                foreach ($response['data'] as $server) {
                    $callback($data, $server);
                }
            } else {
                $data = array_merge($data, $response['data']);
            }
            $page++;
            if (!$response['links']['next']) {
                break;
            }
        }

        return $data;
    }
}