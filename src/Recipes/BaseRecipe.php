<?php
namespace Mittwald\Deployer\Recipes;

use Mittwald\ApiClient\Generated\V2\Schemas\Project\Project;
use Mittwald\ApiClient\MittwaldAPIV2Client;
use function Deployer\get;

class BaseRecipe
{
    public static function getClient(): MittwaldAPIV2Client
    {
        return MittwaldAPIV2Client::newWithToken(get('mittwald_token'));
    }

    public static function getProject(): Project
    {
        return Project::buildFromInput(get('mittwald_project'), validate: false);
    }
}