<?php
declare(strict_types=1);

namespace App\Controllers;
use App\Services\SteamMarketService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * Steam Market Controller
 *
 * Handles endpoints related to retrieving item prices from the Steam Market.
 */
//class SteamMarketController
{
    /**
     * Steam Market service instance for API calls
     * @var SteamMarketService
     */
    private SteamMarketService $steamService;  

    //getItemPrice
    //searchItems
    //getPopularItems
    //getTrendingItems
    //getCategories
    //@TODO FIRST IMPLEMENT OTHER CONTROLLERS AND SERVICES
}