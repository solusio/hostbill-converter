<?php

error_reporting(E_ERROR | E_PARSE);

class DatabaseService
{
    public const SolusVMv1 = 'solusvm';
    public const SolusVMv2 = 'solusio';

    public const VARIABLE_OS = 'os';
    public const VARIABLE_PLAN = 'vpsplan';
    public const VARIABLE_LOCATION = 'location';

    private $db;

    public function __construct()
    {
        $this->db = HBRegistry::singleton()->getObject('db');
    }

    public function getModule(string $product): int
    {
        $q = $this->db->prepare("select id from hb_modules_configuration where filename = \"class.$product.php\";");
        $q->execute();
        $data = $q->fetch(PDO::FETCH_ASSOC);
        return (int)$data['id'];
    }

    public function getServerToApiConnection(int $module): int
    {
        $q = $this->db->prepare('select server from hb_products_modules where module = ?');
        $q->execute([$module]);
        $data = $q->fetch(PDO::FETCH_ASSOC);

        return (int)$data['server'];
    }

    public function getConnection(int $server): array
    {
        $q = $this->db->prepare('select * from hb_servers where id = ?');
        $q->execute([$server]);
        return $q->fetch(PDO::FETCH_ASSOC);
    }

    public function getOrderType(int $id): int
    {
        $q = $this->db->prepare('select ptype from hb_categories where id = ?');
        $q->execute([$id]);
        $d = $q->fetch(PDO::FETCH_ASSOC);
        return (int)$d['ptype'];
    }

    // Hostbill entities for virtual servers
    public function getAccounts(int $productId): array
    {
        $q = $this->db->prepare('select * from hb_accounts where product_id = ?;');
        $q->execute([$productId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProducts(int $module): array
    {
        $q = $this->db->prepare('select * from hb_products_modules left join hb_products on id = product_id where module = ?;');
        $q->execute([$module]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateAccount(int $id, int $serverId, string $details, int $productId): void
    {
        $q = $this->db->prepare("update hb_accounts set server_id = ?, extra_details = ?, product_id = ? where id = ?;");
        $q->execute([$serverId, $details, $productId, $id]);
    }

    public function updateProduct(int $productId, int $orderPage, int $orderType): void
    {
        $q = $this->db->prepare("update hb_products set category_id = ?, type = ? where id = ?;");
        $q->execute([$orderPage, $orderType, $productId]);
    }

    public function updateProductModule(int $productId, int $module, int $serverId, string $options): void
    {
        $q = $this->db->prepare("update hb_products_modules set module = ?, server = ?, options = ? where product_id = ?;");
        $q->execute([$module, $serverId, $options, $productId]);
    }

    public function removeWidgets(int $productId): void
    {
        $q = $this->db->prepare("delete from hb_widgets where target_id=?;");
        $q->execute([$productId]);
    }

    public function addWidgets(int $productId): void
    {
        $q = $this->db->prepare("INSERT INTO hb_widgets (target_id, widget_id, name) select $productId, id, name from hb_widgets_config where widget like 'solusio_%';");
        $q->execute();
    }

    public function getConfigType(): int
    {
        $q = $this->db->prepare('select id from hb_config_items_types where type = "select"');
        $q->execute();
        $d = $q->fetch(PDO::FETCH_ASSOC);
        return (int)$d['id'];
    }

    public function addConfigItemCategory(int $productId, int $type, string $variable, int $sortOrder): int
    {
        $config = [
            'conditionals' => [],
            'addemptyoption' => 0,
            'hideontransfer' => 0,
        ];

        $map = [
            self::VARIABLE_OS => 'OS Template',
            self::VARIABLE_PLAN => 'Plan',
            self::VARIABLE_LOCATION => 'Cloud Location',
        ];

        $sql = <<<EOT
INSERT INTO hb_config_items_cat (type, required, name, variable, description, category, product_id, options, config, sort_order, group_id)
VALUES (?, 0, ?, ?, '', 'software', ?, ?, ?, ?, 0);
EOT;

        $q = $this->db->prepare($sql);
        $q->execute([
            $type,
            $map[$variable],
            $variable,
            $productId,
            ConfigOption::OPTION_SHOWCART | ConfigOption::OPTION_REQUIRED,
            serialize($config),
            $sortOrder,
        ]);

        $q = $this->db->prepare('select id from hb_config_items_cat where variable = ? and product_id = ?;');
        $q->execute([$variable, $productId]);
        $d = $q->fetch(PDO::FETCH_ASSOC);
        return (int)$d['id'];
    }

    public function cleanConfigCategories(int $productId): void
    {
        $q = $this->db->prepare('DELETE FROM hb_config_items_cat WHERE product_id = ?;');
        $q->execute([$productId]);
    }

    public function cleanConfigItems(int $productId): void
    {
        $q = $this->db->prepare('DELETE FROM hb_config_items WHERE category_id IN (SELECT id FROM hb_config_items_cat WHERE product_id = ?);');
        $q->execute([$productId]);
    }

    public function addConfigItems(int $categoryId, string $name, int $value, int $sortOrder): void
    {
        $q = $this->db->prepare('INSERT INTO hb_config_items (category_id, name, variable_id, sort_order) values (?, ?, ?, ?)');
        $q->execute([$categoryId, $name, $value, $sortOrder]);
    }

    public function getProductTypeId(string $type): int
    {
        $q = $this->db->prepare('select id from hb_product_types where type = ?');
        $q->execute([$type]);
        $d = $q->fetch(PDO::FETCH_ASSOC);
        return (int)$d['id'];
    }

    public function getCategories(int $ptype): array
    {
        $q = $this->db->prepare('select * from hb_categories where ptype = ? order by parent_id, id');
        $q->execute([$ptype]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function copyCategory(array $data, int $parentId, int $ptype): int
    {
        $postfix = "v2";

        $q = $this->db->prepare('select max(sort_order) as last from hb_categories');
        $q->execute();
        $d = $q->fetch(PDO::FETCH_ASSOC);
        $sortOrder = (int)$d['last'];

        $sql = <<<EOT
INSERT INTO hb_categories (parent_id, contains, module, name, description, visible, sort_order, template, ctype, ptype, slug, opconfig, scenario_id)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
EOT;

        $name = $data['name'] . ' ' . $postfix;
        $q = $this->db->prepare($sql);
        $q->execute([
            $parentId,
            $data['contains'],
            $data['module'],
            $name,
            $data['description'],
            $data['visible'],
            $sortOrder + 1,
            $data['template'],
            $data['ctype'],
            $ptype,
            $data['slug'] . '-' . $postfix,
            $data['opconfig'],
            $data['scenario_id'],
        ]);

        $q = $this->db->prepare('select id from hb_categories where name = ?');
        $q->execute([$name]);
        $d = $q->fetch(PDO::FETCH_ASSOC);
        return (int)$d['id'];
    }

    public function copyProduct(array $data, int $orderPage, int $orderType): int
    {
        $sql = <<<EOT
INSERT INTO hb_products (category_id, type, name, description, visible, domain_options, stock, qty, autosetup, subdomain, owndomain, owndomainwithus, tax, tax_group_id, upgrades, sort_order, client_limit, hostname, autohostname, username_generation, p_options, code, contract_id, layout, metadata)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
 
EOT;
        $q = $this->db->prepare($sql);
        $q->execute([
            $orderPage,
            $orderType,
            $data['name'],
            $data['description'],
            $data['visible'],
            $data['domain_options'],
            $data['stock'],
            $data['qty'],
            $data['autosetup'],
            $data['subdomain'],
            $data['owndomain'],
            $data['owndomainwithus'],
            $data['tax'],
            $data['tax_group_id'],
            $data['upgrades'],
            $data['sort_order'],
            $data['client_limit'],
            $data['hostname'],
            $data['autohostname'],
            $data['username_generation'],
            $data['p_options'],
            $data['code'],
            $data['contract_id'],
            $data['layout'],
            $data['metadata'],
        ]);

        $q = $this->db->prepare('select id from hb_products where name = ? and category_id = ?');
        $q->execute([$data['name'], $orderPage]);
        $d = $q->fetch(PDO::FETCH_ASSOC);
        return (int)$d['id'];
    }

    public function copyProductModule(int $productId, int $main, int $module, int $serverId, string $options): void
    {
        $sql = <<<EOT
INSERT INTO hb_products_modules (product_id, main, module, server, options)
VALUES (?, ?, ?, ?, ?);
EOT;
        $q = $this->db->prepare($sql);
        $q->execute([
            $productId,
            $main,
            $module,
            $serverId,
            $options,
        ]);
    }

    public function beginTransaction(): void {
        $this->db->beginTransaction();
    }

    public function rollback(): void {
        $this->db->rollback();
    }

    public function commit(): void {
        $this->db->commit();
    }
}