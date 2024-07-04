<?php

declare(strict_types=1);

namespace PhpMyAdmin\Controllers;

use PhpMyAdmin\Config;
use PhpMyAdmin\ConfigStorage\Relation;
use PhpMyAdmin\Current;
use PhpMyAdmin\Http\Response;
use PhpMyAdmin\Http\ServerRequest;
use PhpMyAdmin\Identifiers\DatabaseName;
use PhpMyAdmin\ResponseRenderer;

use const SQL_DIR;

/**
 * Displays status of phpMyAdmin configuration storage
 */
final class CheckRelationsController implements InvocableController
{
    public function __construct(private readonly ResponseRenderer $response, private readonly Relation $relation)
    {
    }

    public function __invoke(ServerRequest $request): Response
    {
        $cfgStorageDbName = $this->relation->getConfigurationStorageDbName();

        $db = DatabaseName::tryFrom(Current::$database);

        // If request for creating the pmadb
        if ($request->hasBodyParam('create_pmadb') && $this->relation->createPmaDatabase($cfgStorageDbName)) {
            $this->relation->fixPmaTables($cfgStorageDbName);
        }

        // If request for creating all PMA tables.
        if ($request->hasBodyParam('fixall_pmadb') && $db !== null) {
            $this->relation->fixPmaTables($db->getName());
        }

        // If request for creating missing PMA tables.
        if ($request->hasBodyParam('fix_pmadb')) {
            $relationParameters = $this->relation->getRelationParameters();
            $this->relation->fixPmaTables((string) $relationParameters->db);
        }

        // Do not use any previous $relationParameters value as it could have changed after a successful fixPmaTables()
        $relationParameters = $this->relation->getRelationParameters();

        $this->response->render('relation/check_relations', [
            'db' => $db?->getName() ?? '',
            'zero_conf' => Config::getInstance()->settings['ZeroConf'],
            'relation_parameters' => $relationParameters->toArray(),
            'sql_dir' => SQL_DIR,
            'config_storage_database_name' => $cfgStorageDbName,
            'are_config_storage_tables_defined' => $this->relation->arePmadbTablesDefined(),
        ]);

        return $this->response->response();
    }
}
