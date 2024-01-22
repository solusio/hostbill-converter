<?php

require __DIR__ . '/../../../../vendor/autoload.php';
require(__DIR__ . '/../../../../hbf/bootstrap.php');
require_once  __DIR__ . '/../solusio/class.solusio.php';

foreach (["commands", "services"] as $folder) {
    foreach (glob("src/converter/$folder/*.php") as $filename) {
        require_once $filename;
    }
}

class Converter
{
    private const ClusterImport = '--cluster-import';
    private const OrderPage = '--order-page';
    private const KVMPlan = '--kvm-plan';
    private const VZPlan = '--vz-plan';
    private const VZOsImage = '--vz-os-image';
    private const KVMOsImage = '--kvm-os-image';
    private const Location = '--location';
    private const Role = '--role';
    private const LimitGroup = '--limit-group';
    private const Accounts = '--accounts';

    private array $arguments;

    public function __construct(array $argv)
    {
        $this->arguments = $argv;
    }

    public function start(): void
    {
        $command = 'help';
        if (isset($this->arguments[1])) {
            $command = $this->arguments[1];
        }

        switch ($command) {
            case 'prepare':
                $clusterImport = (int)$this->getArgument(self::ClusterImport);
                (new PrepareCommand())->handle($clusterImport);
                break;
            case 'reconfigure':
                $this->reconfigure();
                break;
            case 'dry-run':
                (new DryRunCommand())->handle();
                break;
            case 'copy-order-pages':
                $this->copyOrderPages();
                break;
            case 'prepare-servers':
                $clusterImport = (int)$this->getArgument(self::ClusterImport);
                (new PrepareServersCommand())->handle($clusterImport);
                break;
            case 'convert-accounts':
                (new ConvertAccountsCommand())->handle();
                break;
            case 'revert-accounts':
                $ids = $this->getArgument(self::Accounts);
                (new RevertAccountsCommand())->handle($ids);
                break;
            case 'help':
            default:
                $this->help();
        }
    }

    private function help(): void
    {
        echo <<<EOT
SolusVM 2 Converter
This command allows you to convert VPS`es of SolusVM 1 Products to VPS`es of SolusVM 2 Products
Available commands:
* help - shows available commands
* prepare - save necessary data from SolusVM 1 and SolusVM 2 to resources directory. Available flags:
    --cluster-import - ID of cluster import in SolusVM 2.
* reconfigure - reconfigures all SolusVM 1 products into the SolusVM 2 products and its accounts. To be sure that all products will be converted run this command with all available flags. Available flags:
    --order-page - ID of order page for SolusVM 2 products (required)
    --limit-group - ID of SolusVM 2 limit group (required)
    --kvm-plan - ID of SolusVM 2 plan with virtualization type KVM, needed if we can't find plan by plan name which is used in SolusVM 1 product
    --vz-plan - ID of SolusVM 2 plan with virtualization type VZ, needed if we can't find plan by plan name which is used in SolusVM 1 product
    --kvm-os-image - ID of SolusVM 2 OS image with virtualization type KVM, needed for KVM products because we don't import KVM OS images from SolusVM 1
    --vz-os-image - ID of SolusVM 2 OS image with virtualization type VZ, needed if we can't find OS image by template which is used in SolusVM 1 product
    --location - ID of SolusVM 2 location, needed if we can't find location by Node Group name which is used in SolusVM 1 product
    --role - ID of SolusVM 2 role (default value is ID of role with name "CLIENT")
* dry-run - shows list of accounts to convert and revert converting command
* copy-order-pages - duplicate SolusVM 1 order page with its products to SolusVM 2 order page with converted products
    --limit-group - ID of SolusVM 2 limit group (required)
    --kvm-plan - ID of SolusVM 2 plan with virtualization type KVM, needed if we can't find plan by plan name which is used in SolusVM 1 product
    --vz-plan - ID of SolusVM 2 plan with virtualization type VZ, needed if we can't find plan by plan name which is used in SolusVM 1 product
    --kvm-os-image - ID of SolusVM 2 OS image with virtualization type KVM, needed for KVM products because we don't import KVM OS images from SolusVM 1
    --vz-os-image - ID of SolusVM 2 OS image with virtualization type VZ, needed if we can't find OS image by template which is used in SolusVM 1 product
    --location - ID of SolusVM 2 location, needed if we can't find location by Node Group name which is used in SolusVM 1 product
    --role - ID of SolusVM 2 role (default value is ID of role with name "CLIENT")
* prepare-servers - download all data from SolusVM 2 to convert SolusVM 1 accounts to SolusVM 2 accounts if its servers were imported
* convert-accounts - convert SolusVM 1 accounts to SolusVM 2 accounts if its servers were imported
* revert-accounts - revert converting of converted to SolusVM 2 accounts to SolusVM 1
    --accounts - list of SolusVM 2 account ID`s to convert them back to SolusVM 1 account
EOT;
    }

    private function reconfigure(): void
    {
        // arguments
        $orderPage = $this->getArgument(self::OrderPage);
        $kvmPlan = $this->getArgument(self::KVMPlan);
        $vzPlan = $this->getArgument(self::VZPlan);
        $kvmOsImage = $this->getArgument(self::KVMOsImage);
        $vzOsImage = $this->getArgument(self::VZOsImage);
        $location = $this->getArgument(self::Location);
        $role = $this->getArgument(self::Role);
        $limitGroup = $this->getArgument(self::LimitGroup);

        if (!$orderPage) {
            echo "--order-page argument is required.\n";
            return;
        }

        if (!$limitGroup) {
            echo "--limit-group argument is required.\n";
            return;
        }

        (new ReconfigureCommand())->handle(
            $orderPage,
            $kvmPlan,
            $vzPlan,
            $kvmOsImage,
            $vzOsImage,
            $location,
            $role,
            $limitGroup,
        );
    }

    private function copyOrderPages(): void
    {
        // arguments
        $kvmPlan = $this->getArgument(self::KVMPlan);
        $vzPlan = $this->getArgument(self::VZPlan);
        $kvmOsImage = $this->getArgument(self::KVMOsImage);
        $vzOsImage = $this->getArgument(self::VZOsImage);
        $location = $this->getArgument(self::Location);
        $role = $this->getArgument(self::Role);
        $limitGroup = $this->getArgument(self::LimitGroup);

        if (!$limitGroup) {
            echo "--limit-group argument is required.\n";
            return;
        }

        (new DuplicateOrderPagesCommand())->handle(
            $kvmPlan,
            $vzPlan,
            $kvmOsImage,
            $vzOsImage,
            $location,
            $role,
            $limitGroup,
        );
    }

    private function getArgument(string $key)
    {
        $arrayKeys = [self::Accounts => true];
        $asArray = isset($arrayKeys[$key]);

        foreach ($this->arguments as $i => $item) {
            $argument = strtolower($item);

            if ($argument === $key && isset($this->arguments[$i + 1])) {
                return $this->parseArgumentValue($this->arguments[$i + 1], $asArray);
            }

            $s = explode('=', $argument);
            if (count($s) === 2 && trim($s[0]) === $key) {
                return $this->parseArgumentValue($s[1], $asArray);
            }
        }

        return '';
    }

    private function parseArgumentValue($value, $asArray)
    {
        if (is_numeric($value)) {
            return $asArray ? [(int)$value] : (int)$value;
        }

        if (strpos($value, ',')) {
            return explode(',', $value);
        }

        return '';
    }
}

$c = new Converter($argv);
$c->start();
echo "\n";