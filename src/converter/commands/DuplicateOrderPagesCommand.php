<?php

class DuplicateOrderPagesCommand extends BaseCommand
{
    /**
     * @param int|string $kvmPlan
     * @param int|string $vzPlan
     * @param int|string $kvmOsImage
     * @param int|string $vzOsImage
     * @param int|string $location
     * @param int|string $role
     * @param int|string $limitGroup
     * @return void
     * @throws Exception
     */
    public function handle(
        $kvmPlan,
        $vzPlan,
        $kvmOsImage,
        $vzOsImage,
        $location,
        $role,
        $limitGroup
    ): void {
        if (!$role) {
            $roles = $this->fileService->load(FileService::ROLES);
            if (isset($roles['CLIENT'])) {
                $role = $roles['CLIENT'];
            }
        }

        $plans = $this->fileService->load(FileService::PLANS);
        $locations = $this->fileService->load(FileService::LOCATIONS);
        $importedLocations = $this->fileService->load(FileService::IMPORTED_LOCATIONS);
        $computeResources = $this->fileService->load(FileService::COMPUTE_RESOURCES);
        $osImages = $this->fileService->load(FileService::OS_IMAGES);

        $configType = $this->db->getConfigType();

        $solusvm1ProductType = $this->db->getProductTypeId('soluspanel');
        $solusvm2ProductType = $this->db->getProductTypeId('solusiotype');
        $categories = $this->db->getCategories($solusvm1ProductType);

        $categoryMap = [];
        foreach ($categories as $category) {
            $parentId = $categoryMap[$category['parent_id']] ?? 0;

            $id = $this->db->copyCategory($category, $parentId, $solusvm2ProductType);
            $categoryMap[$category['id']] = $id;
        }

        $v1Module = $this->db->getModule(DatabaseService::SolusVMv1);
        $v1Products = $this->db->getProducts($v1Module);

        echo "Number of founded products: " . count($v1Products) . "\n";

        $productMap = [];
        foreach ($v1Products as $product) {
            $orderPage = $categoryMap[$product['category_id']] ?? 0;
            if (!$orderPage) {
                continue;
            }
            $id = $this->db->copyProduct($product, $orderPage, $solusvm2ProductType);
            $productMap[$product['id']] = $id;

            $oldOptions = unserialize($product['options']);
            $virtualizationType = $oldOptions['option1'] === 'openvz' ? ApiV2Service::VZ : ApiV2Service::KVM;

            $categorySortOrder = 0;

            // get Plan
            $planId = '';
            if ($oldOptions['option5']) {
                if (isset($plans[$virtualizationType][$oldOptions['option5']])) {
                    $planId = $plans[$virtualizationType][$oldOptions['option5']]['id'];
                }
            }
            if (!$planId) {
                $planId = $virtualizationType === ApiV2Service::VZ ? $vzPlan : $kvmPlan;
            }
            if (!$planId) {
                $categoryId = $this->db->addConfigItemCategory(
                    $product['product_id'],
                    $configType,
                    DatabaseService::VARIABLE_PLAN,
                    ++$categorySortOrder,
                );
                $itemSortOrder = 0;
                foreach ($plans[$virtualizationType] as $key => $value) {
                    if ($value['is_visible']) {
                        $this->db->addConfigItems($categoryId, $key, $value['id'], ++$itemSortOrder);
                    }
                }
            }

            // get Location
            if ($oldOptions['nodegroup'] !== '' && isset($importedLocations[$oldOptions['nodegroup']])) {
                $locationId = $importedLocations[$oldOptions['nodegroup']];
            } elseif (count($oldOptions['option5']) === 1) {
                if (isset($computeResources[$oldOptions['option5'][0]])) {
                    $locationId = $computeResources[$oldOptions['option5'][0]];
                }
            } else {
                $locationId = $location;
            }

            if (!$locationId) {
                $categoryId = $this->db->addConfigItemCategory(
                    $product['product_id'],
                    $configType,
                    DatabaseService::VARIABLE_LOCATION,
                    ++$categorySortOrder,
                );
                $itemSortOrder = 0;
                foreach ($locations as $key => $value) {
                    if ($value['is_visible']) {
                        $this->db->addConfigItems($categoryId, $key, $value['id'], ++$itemSortOrder);
                    }
                }
            }

            // get OS Image
            $osImageId = '';
            if ($oldOptions['option4']) {
                if (isset($osImages[$virtualizationType][$oldOptions['option4']])) {
                    $osImageId = $osImages[$virtualizationType][$oldOptions['option4']]['id'];
                }
            }
            if (!$osImageId) {
                $osImageId = $virtualizationType === ApiV2Service::VZ ? $vzOsImage : $kvmOsImage;
            }
            if (!$osImageId) {
                $categoryId = $this->db->addConfigItemCategory(
                    $product['product_id'],
                    $configType,
                    DatabaseService::VARIABLE_OS,
                    ++$categorySortOrder,
                );
                $itemSortOrder = 0;
                foreach ($osImages[$virtualizationType] as $value) {
                    if ($value['is_visible']) {
                        $this->db->addConfigItems($categoryId, $value['name'], $value['id'], ++$itemSortOrder);
                    }
                }
            }

            $options = [
                Solusio::O_TYPE => $oldOptions[Solusio::O_TYPE],
                Solusio::O_FLAVOR => '',
                Solusio::O_VPS_PLAN => $planId,
                Solusio::O_LOCATION => $locationId,
                Solusio::O_USERROLE => $role,
                Solusio::O_LIMITGROUP => $limitGroup,
                Solusio::O_IPSV4 => '',
                Solusio::O_IPSV6 => '',
                Solusio::O_SOFTWARE_MODE => 'default',
                Solusio::O_OS => $osImageId,
                Solusio::O_APPLICATION => '',
                Solusio::O_BACKUPS_LIMIT => '',
                Solusio::O_USER_DATA => '',
            ];

            $this->db->copyProductModule(
                $id,
                $product['main'],
                $this->v2Api->module,
                $this->v2Api->serverId,
                serialize($options),
            );

            $this->db->addWidgets($product['product_id']);
        }

        echo "Number of converted products: " . count($productMap) . "\n";

        $this->fileService->save(FileService::PACKAGES, $productMap);
    }
}