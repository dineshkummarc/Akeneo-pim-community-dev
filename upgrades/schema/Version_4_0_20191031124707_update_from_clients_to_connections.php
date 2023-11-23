<?php declare(strict_types=1);

namespace Pim\Upgrade\Schema;

use Akeneo\Connectivity\Connection\Application\Settings\Command\CreateConnectionCommand;
use Akeneo\Connectivity\Connection\Application\Settings\Command\CreateConnectionHandler;
use Akeneo\Connectivity\Connection\Domain\Settings\Exception\ConstraintViolationListException;
use Akeneo\Connectivity\Connection\Domain\Settings\Model\Read\ConnectionWithCredentials;
use Akeneo\Connectivity\Connection\Domain\Settings\Model\ValueObject\FlowType;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class Version_4_0_20191031124707_update_from_clients_to_connections
    extends AbstractMigration
    implements ContainerAwareInterface
{
    /** @var ContainerInterface */
    private $container;

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('SELECT "disable migration warning"');

        $this->migrateToConnections();
    }

    public function down(Schema $schema) : void
    {
        $this->throwIrreversibleMigrationException();
    }

    private function migrateToConnections(): void
    {
        $selectClients = <<< SQL
    SELECT id, label FROM pim_api_client
SQL;
        $clientsStatement = $this->dbalConnection()->executeQuery($selectClients);
        $clients = $clientsStatement->fetchAllAssociative();

        if (empty($clients)) {
            $this->write('No API connection to migrate.');

            return;
        }

        $this->write(sprintf('%s API connections found. They will be migrate to Connection.', count($clients)));

        $clients = $this->generateConnectionsCode($clients);

        foreach ($clients as $client) {
            $connectionLabel = substr(str_pad($client['label'], 3, '_'), 0, 97);

            $connection = $this->createConnection($client['code'], $connectionLabel);

            $clientToDelete = $this->retrieveAutoGeneratedClientId($client['code']);

            $this->updateConnectionWithOldClient($client['code'], $client['id']);

            $this->deleteAutoGeneratedClient($clientToDelete);

            $this->write(sprintf('API connection migrated to %s!', $connection->code()));
        }

        $this->write(sprintf('%s Connections created.', count($clients)));
    }

    /**
     * The Connection has been created with an auto generated client.
     *
     * @param string $code
     *
     * @return string
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function retrieveAutoGeneratedClientId(string $code)
    {
        $retrieveNewConnectionClientId = <<< SQL
SELECT client_id
FROM akeneo_connectivity_connection
WHERE code = :code
SQL;
        $retrieveStatement = $this->dbalConnection()->executeQuery($retrieveNewConnectionClientId, ['code' => $code]);

        return $retrieveStatement->fetchOne();
    }

    /**
     * Connection has been created with an auto generated client. The Connection need to be updated with the old client id.
     *
     * @param string $code
     * @param string $clientId
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function updateConnectionWithOldClient(string $code, string $clientId): void
    {
        $updateConnectionQuery = <<< SQL
UPDATE akeneo_connectivity_connection
SET client_id = :client_id
WHERE code = :code;
SQL;
        $this->dbalConnection()->executeQuery(
            $updateConnectionQuery,
            [
                'code' => $code,
                'client_id' => $clientId,
            ]
        );
    }

    /**
     * Once the Connection is updated with old client id we remove the auto generated client.
     *
     * @param string $clientId
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    private function deleteAutoGeneratedClient(string $clientId): void
    {
        $deleteClientQuery = <<< SQL
DELETE from pim_api_client WHERE id = :client_id;
SQL;
        $this->dbalConnection()->executeQuery($deleteClientQuery, ['client_id' => $clientId]);
    }

    /**
     * Generate the code of the Connection in terms of the label of the client.
     * If several Connection have the same code, it auto increments codes.
     *
     * @param array $clients
     *
     * @return array
     */
    private function generateConnectionsCode(array $clients): array
    {
        array_walk($clients, function (&$client) {
            $client['code'] = $this->slugify($client['label']);
        });

        return $this->makeConnectionsCodeUnique($clients);
    }

    /**
     * Auto increments Connections code if needed.
     *
     * @param array $clients
     *
     * @return array
     */
    private function makeConnectionsCodeUnique(array $clients): array
    {
        $codeOccurence = array_count_values(array_column($clients, 'code'));
        foreach ($clients as $index => $client) {
            $code = $client['code'];

            if (1 < $codeOccurence[$code]) {
                $clients[$index]['code'] = sprintf('%s_%s', $code, (string) $codeOccurence[$code]);
                $codeOccurence[$code]--;
            }
        }

        return $clients;
    }

    private function dbalConnection(): DbalConnection
    {
        return $this->container->get('database_connection');
    }

    private function createConnection(string $connectionCode, string $connectionLabel): ConnectionWithCredentials
    {
        try {
            $command = new CreateConnectionCommand(
                $connectionCode,
                $connectionLabel,
                FlowType::OTHER
            );
            return $this->container
                ->get('akeneo_connectivity.connection.application.handler.create_connection')
                ->handle($command);
        } catch (ConstraintViolationListException $constraintViolationListException) {
            foreach ($constraintViolationListException->getConstraintViolationList() as $constraintViolation) {
                $this->write($constraintViolation->getMessage());
            }
            throw $constraintViolationListException;
        }
    }

    private function slugify(string $label): string
    {
        // Adds chars if less than 3 and substr some if more than 97
        $truncated = substr(str_pad($label, 3, '_'), 0, 97);

        return preg_replace('/[^A-Za-z0-9]/', '_', $truncated);
    }
}
