<?php
namespace Mittwald\Deployer\Recipes;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Mittwald\ApiClient\Generated\V2\Client;
use Mittwald\ApiClient\Generated\V2\Schemas\Project\Project;
use Mittwald\ApiClient\MittwaldAPIV2Client;
use function Deployer\get;
use function Deployer\has;
use function Mittwald\Deployer\get_array;
use function Mittwald\Deployer\get_str;

class BaseRecipe
{
    public static function getClient(): Client
    {
        if (has('mittwald_client')) {
            $client = get('mittwald_client');
            assert($client instanceof Client);
            return $client;
        }

        return MittwaldAPIV2Client::newWithToken(get_str('mittwald_token'));
    }

    public static function getFilesystem(): Filesystem
    {
        if (has('mittwald_filesystem')) {
            $fs = get('mittwald_filesystem');
            assert($fs instanceof Filesystem);
            return $fs;
        }

        return new Filesystem(new LocalFilesystemAdapter(getcwd()));
    }

    public static function getProject(): Project
    {
        return Project::buildFromInput(get_array('mittwald_project'), validate: false);
    }
}