<?php
declare(strict_types=1);

class PrepareCommand extends BaseCommand
{
    public function handle(int $clusterImport): void
    {
        if (!$clusterImport) {
            $clusterImports = $this->v2Api->getClusterImports();
            if (count($clusterImports) === 1) {
                $clusterImport = $clusterImports[0]['id'];
            } else {
                throw new Exception("Can't find cluster import! Pass it using --cluster-import argument");
            }
        }

        // imported entities
        $this->fileService->save(
            FileService::IMPORTED_SERVERS,
            $this->v2Api->getClusterImportEntities($clusterImport, 'servers'),
        );
        $this->fileService->save(
            FileService::IMPORTED_LOCATIONS,
            $this->v2Api->getClusterImportEntities($clusterImport, 'locations'),
        );

        $this->fileService->save(FileService::SERVERS, $this->v2Api->getSolusVm2Servers());
        $this->fileService->save(FileService::COMPUTE_RESOURCES, $this->v2Api->getComputeResourceLocations());
        $this->fileService->save(FileService::OS_IMAGES, $this->v2Api->getOsImages());
        $this->fileService->save(FileService::PLANS, $this->v2Api->getPlans());
        $this->fileService->save(FileService::LOCATIONS, $this->v2Api->getLocations());
        $this->fileService->save(FileService::ROLES, $this->v2Api->getRoles());
        echo "Done!";
    }
}