<?php

class ReconfigureCommand extends BaseCommand
{
    /**
     * @param int|string $orderPage
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
        $orderPage,
        $kvmPlan,
        $vzPlan,
        $kvmOsImage,
        $vzOsImage,
        $location,
        $role,
        $limitGroup
    ): void {
        $orderType = $this->db->getOrderType($orderPage);
        if (!$orderType) {
            echo "failed to find order type of order page $orderPage.\n";
            return;
        }

        if (!$role) {
            $roles = $this->fileService->load(FileService::ROLES);
            if (isset($roles['CLIENT'])) {
                $role = $roles['CLIENT'];
            }
        }

        $servers = $this->fileService->load(FileService::SERVERS);
        $importedServers = $this->fileService->load(FileService::IMPORTED_SERVERS);
        $plans = $this->fileService->load(FileService::PLANS);
        $locations = $this->fileService->load(FileService::LOCATIONS);
        $importedLocations = $this->fileService->load(FileService::IMPORTED_LOCATIONS);
        $computeResources = $this->fileService->load(FileService::COMPUTE_RESOURCES);
        $osImages = $this->fileService->load(FileService::OS_IMAGES);

        $configType = $this->db->getConfigType();

        $v1Module = $this->db->getModule(DatabaseService::SolusVMv1);
        $v1Products = $this->db->getProducts($v1Module);

        $totalProducts = count($v1Products);
        $totalServers = 0;
        $updatedProducts = 0;
        $updatedServers = 0;

        foreach ($v1Products as $product) {
            $this->db->beginTransaction();

            // Convert accounts (server entities in Hostbill) of v1 to v2
            $accounts = $this->db->getAccounts((int)$product['product_id']);
            $totalServers += count($accounts);

            // Update virtual servers (Hostbill accounts)
            foreach ($accounts as $account) {
                $data = unserialize($account['extra_details']);

                $v1serverId = (int)$data['option6'];
                if (isset($importedServers[$v1serverId])) {
                    $v2serverId = (int)$importedServers[$v1serverId];

                    if (isset($servers[$v2serverId]) && $servers[$v2serverId]['name'] === $account['domain']) {
                        $data = [
                            'option6' => $v2serverId,
                            'userid' => $servers[$v2serverId]['user_id'],
                        ];

                        $this->db->updateAccount(
                            $account['id'],
                            $this->v2Api->serverId,
                            serialize($data),
                            $product['product_id'],
                        );
                        continue;
                    }

                    if (!isset($servers[$v2serverId])) {
                        echo "There is no server with id $v2serverId for " . $account['domain'] . "\n";
                    }
                    if ($servers[$v2serverId]['name'] !== $account['domain']) {
                        echo "Founded domain " . $servers[$v2serverId]['name'] . "is not equal with expected " . $account['domain'] . "\n";
                    }
                } else {
                    echo "Not found imported server for " . $account['domain'] . "\n";
                }

                $this->db->rollback();
                continue 2;
            }

            // Convert product
            $oldOptions = unserialize($product['options']);
            $virtualizationType = $oldOptions['option1'] === 'openvz' ? ApiV2Service::VZ : ApiV2Service::KVM;

            $this->db->cleanConfigItems($product['product_id']);
            $this->db->cleanConfigCategories($product['product_id']);
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

            // Move product to other category
            $this->db->updateProduct($product['product_id'], $orderPage, $orderType);

            // Convert product to v2
            $this->db->updateProductModule(
                $product['product_id'],
                $this->v2Api->module,
                $this->v2Api->serverId,
                serialize($options),
            );

            $this->db->removeWidgets($product['product_id']);

            $this->db->addWidgets($product['product_id']);

            $this->db->commit();

            $updatedProducts++;
            $updatedServers += count($accounts);
        }

        echo "Total number of products: $totalProducts with $totalServers servers;\n";
        echo "Number of updated products: $updatedProducts with $updatedServers servers;\n";
    }
}