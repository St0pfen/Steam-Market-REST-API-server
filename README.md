## ⚠️ Disclaimer: 
```diff
-This is an unofficial third-party API and is not affiliated with or endorsed by Valve Corporation or Steam.
-All Steam-related trademarks and data belong to their respective owners.
```

A PHP REST API for retrieving Steam Market data with comprehensive logging and CORS support.

# Steam Market REST API

A PHP REST API for retrieving Steam Market/Profile and Game data

## 🚀 Features

- **Steam Market Data**: Prices, search functionality, popular items
- **Steam Profile Data**: Profile info, inventory, friends, recent games
- **Item Images**: Automatic item image URLs for all endpoints
- **Multiple Steam Apps**: CS:GO, Dota 2, TF2, Rust, Unturned
- **App Search**: Dynamic Steam app search by name
- **REST API**: Clean JSON endpoints for Python/JavaScript clients
- **Error Handling**: Robust error handling and logging
- **IP Logging**: Complete request logging with IP tracking
- **Documentation**: Integrated API documentation

## 📖 Documentation
[I tried to create a comprehensive Wiki in the Wiki-TAB](https://github.com/St0pfen/Steam-Market-REST-API-server/wiki)

## 💚 Contributing
Thank you for your interest in contributing to the Steam Market REST API! This document outlines the process for contributing to the project.
If you are an absolute beginner, so am I. All ideas and requests are welcome!
We all have to start somewhere...
[📖More info in the wiki📖](https://github.com/St0pfen/Steam-Market-REST-API-server/wiki/Contributing)

## 🙏 Used Libraries
- **allyans3/steam-market-api-v2** - MIT License
- **slim/slim** - MIT License
- **slim/psr7** - MIT License  
- **monolog/monolog** - MIT License
- **vlucas/phpdotenv** - BSD-3-Clause License
- **php-di/php-di** - MIT License
- **phpunit/phpunit** (dev) - BSD-3-Clause License

## 📄 License
This work is licensed under a [Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International License](http://creativecommons.org/licenses/by-nc-sa/4.0/).

[![Creative Commons License](https://i.creativecommons.org/l/by-nc-sa/4.0/88x31.png)](http://creativecommons.org/licenses/by-nc-sa/4.0/)

---




# 🔎 A quick starter guide and some examples
## 📋 Prerequisites

- PHP 7.4+ or 8.0+
- Composer

## 🛠️ Installation
[📖Detailed installation guide📖](https://github.com/St0pfen/Steam-Market-REST-API-server/wiki/Installation)
```bash
# Clone repository
git clone <repository-url>
cd php-rest-api

# Install dependencies
composer install

# Create environment file
cp .env.example .env

# Start development server
php -S localhost:8000 -t public/
```

## ⚙️ Configuration
[📖Detailed configuration guide📖](https://github.com/St0pfen/Steam-Market-REST-API-server/wiki/Configuration)

### Environment Variables
Create a `.env` file in the root directory with the following variables:

```env
# Steam API Configuration
STEAM_API_KEY=your_steam_api_key_here

# Application Configuration
APP_NAME="Steam REST API"
APP_VERSION="1.0.0"
APP_DEBUG=false
APP_BASE_URL="http://localhost:8000"

# CORS Configuration
CORS_ALLOW_ORIGIN="*"
CORS_ALLOW_HEADERS="X-Requested-With, Content-Type, Accept, Origin, Authorization"
CORS_ALLOW_METHODS="GET, POST, PUT, DELETE, PATCH, OPTIONS"
```

### Steam API Key

Some Steam Profile endpoints require a Steam Web API key:

1. **Get your Steam API key** from: https://steamcommunity.com/dev/apikey
2. **Add it to your `.env`** file as `STEAM_API_KEY=your_key_here`

## 📡 API Endpoints

### Shop Endpoints
- `GET /api/v1/steam/shop/status` - API Status
- `GET /api/v1/steam/shop/apps` - Supported Steam Apps
- `GET /api/v1/steam/shop/find-app/{app-name}` - Search app by name
- `GET /api/v1/steam/shop/app/{appId}` - Get app details

### Market Endpoints
- `GET /api/v1/steam/market/item/{itemName}` - Get item price
- `GET /api/v1/steam/market/search/{itemName}` - Search items
- `GET /api/v1/steam/market/popular` - Popular items

### Profile Endpoints
- `GET /api/v1/steam/profile/{identifier}` - Get Steam profile info
- `GET /api/v1/steam/profile/summary/{identifier}` - Get Steam profile info (alias)
- `GET /api/v1/steam/profile/friends/{identifier}` - Get friends list (requires API key)
- `GET /api/v1/steam/profile/recent-games/{identifier}` - Get recently played games (requires API key)

### Inventory Endpoints
- `GET /api/v1/steam/inventory/highest-value/{identifier}` - Get highest value (Covert) items in inventory
- `GET /api/v1/steam/inventory/cs2/{identifier}` - Get CS2 inventory (default)
- `GET /api/v1/steam/inventory/{appId}/{identifier}` - Get inventory for specific app

### General API Endpoints
- `GET /api/v1/test` - API Test
- `GET /api/v1/docs` - Complete Documentation

---

## 🔍 Examples
[📖More examples📖](https://github.com/St0pfen/Steam-Market-REST-API-server/wiki/Examples)
### Search App by Name
```bash
curl "http://localhost:8000/api/v1/steam/shop/find-app/Counter-Strike"
curl "http://localhost:8000/api/v1/steam/shop/find-app/Dota"
```

### Get App Details
```bash
curl "http://localhost:8000/api/v1/steam/shop/app/730"
curl "http://localhost:8000/api/v1/steam/shop/app/570"
```

### Get Item Price
```bash
curl "http://localhost:8000/api/v1/steam/market/item/AK-47%20%7C%20Redline%20(Field-Tested)"
```

### Search Items
```bash
curl "http://localhost:8000/api/v1/steam/market/search/AK-47?count=5&app_id=730"
```

### Get Popular Items
```bash
curl "http://localhost:8000/api/v1/steam/market/popular"
```

### Steam Profile Examples
```bash
# Get profile info (works with Steam64 ID, vanity URL, or profile URL)
curl "http://localhost:8000/api/v1/steam/profile/76561198037867621"
curl "http://localhost:8000/api/v1/steam/profile/StuntmanLT"
curl "http://localhost:8000/api/v1/steam/profile/https://steamcommunity.com/id/StuntmanLT/"

# Get inventory (CS2 by default, add /{appId}/ for other apps)
curl "http://localhost:8000/api/v1/steam/inventory/cs2/76561198037867621"
curl "http://localhost:8000/api/v1/steam/inventory/570/76561198037867621"

# Get friends list (requires STEAM_API_KEY)
curl "http://localhost:8000/api/v1/steam/profile/friends/76561198037867621"

# Get recent games (requires STEAM_API_KEY)
curl "http://localhost:8000/api/v1/steam/profile/recent-games/76561198037867621"
```

## 📄 API Response Examples

### Item Price Response
```json
{
  "item_name": "AK-47 | Redline (Field-Tested)",
  "lowest_price": 2456,
  "lowest_price_str": "$24.56",
  "volume": "42",
  "median_price": 2634,
  "median_price_str": "$26.34",
  "image_url": "https://community.cloudflare.steamstatic.com/economy/image/fWFc82js0fmoRAP-qOIPu5THSWqfSmTELLqcUywGkijVjZULUrsm1j-9xgEObwgfEh_nvjlWhNzZCveCDfIBj98xqodQ2CZknz56P7fiDzZ2TQXJVfdhX_Dpsw",
  "app_id": 730,
  "success": true,
  "timestamp": "2024-01-15 12:30:45"
}
```

### Item Search Response
```json
{
  "items": [
    {
      "name": "AK-47 | Redline (Field-Tested)",
      "hash_name": "AK-47 | Redline (Field-Tested)",
      "sell_price": 2456,
      "sell_price_text": "$24.56",
      "sell_listings": 42,
      "buy_price": 2398,
      "buy_price_text": "$23.98",
      "image_url": "https://community.cloudflare.steamstatic.com/economy/image/fWFc82js0fmoRAP-qOIPu5THSWqfSmTELLqcUywGkijVjZULUrsm1j-9xgEObwgfEh_nvjlWhNzZCveCDfIBj98xqodQ2CZknz56P7fiDzZ2TQXJVfdhX_Dpsw",
      "app_id": 730
    }
  ],
  "query": "AK-47",
  "app_id": 730,
  "count": 1,
  "success": true,
  "timestamp": "2024-01-15 12:30:45"
}
```

### Steam Profile Response
```json
{
  "profile": {
    "steamid": "76561198037867621",
    "personaname": "Stuntman PixelJudge.com",
    "profileurl": "https://steamcommunity.com/id/StuntmanLT/",
    "avatar": "https://avatars.steamstatic.com/e1eaa4aabfdca1e787ef7c89ce6192c00dcac688.jpg",
    "avatarmedium": "https://avatars.steamstatic.com/e1eaa4aabfdca1e787ef7c89ce6192c00dcac688_medium.jpg",
    "avatarfull": "https://avatars.steamstatic.com/e1eaa4aabfdca1e787ef7c89ce6192c00dcac688_full.jpg",
    "personastate": "Offline",
    "realname": "Simon",
    "timecreated": "2011-02-07 14:36:16"
  },
  "success": true,
  "timestamp": "2025-07-01 18:35:05"
}
```

### Steam Inventory Response
```json
{
  "inventory": {
    "steamid": "76561198037867621",
    "appid": 730,
    "contextid": 2,
    "total_inventory_count": 6,
    "items": [
      {
        "assetid": "41123236313",
        "name": "Music Kit | Halo, The Master Chief Collection",
        "market_name": "Music Kit | Halo, The Master Chief Collection",
        "type": "High Grade Music Kit",
        "rarity": "High Grade",
        "tradable": 0,
        "marketable": 0,
        "icon_url": "https://community.cloudflare.steamstatic.com/economy/image/-9a81dlWLwJ2UUGcVs_nsVtzdOEdtWwKGZZLQHTxDZ7I56KU0Zwwo4NUX4oFJZEHLbXO9B9WLbU5oA9OA0TRS-uSh56dUgktfFEFsO6ge1I5i6SZcm0X6oqyzNLSkaHyYeLQx2oD68Zzju3Cp8LlhlM90V8s0w"
      }
    ],
    "success": true
  },
  "success": true,
  "timestamp": "2025-07-01 18:38:58"
}
```

### Steam Friends Response
```json
{
  "friends": {
    "steamid": "76561198037867621",
    "friends_count": 189,
    "friends": [
      {
        "steamid": "76561197960287930",
        "relationship": "friend",
        "friend_since": "2012-03-15 10:20:30"
      }
    ],
    "success": true
  },
  "success": true,
  "timestamp": "2025-07-01 18:40:03"
}
```

### Steam Recent Games Response
```json
{
  "recent_games": {
    "steamid": "76561198037867621",
    "total_count": 6,
    "games": [
      {
        "appid": 2668510,
        "name": "Red Dead Redemption",
        "playtime_2weeks": 1363,
        "playtime_forever": 1363,
        "img_icon_url": "https://media.steampowered.com/steamcommunity/public/images/apps/2668510/c48b2bcf02c05ad184c5f443c8969c9ee304c883.jpg"
      }
    ],
    "success": true
  },
  "success": true,
  "timestamp": "2025-07-01 18:40:45"
}
```

## ⚡️ Project Modernization & Error Handling (2025)

- All controllers and endpoints now use a centralized error handling pattern:
  - Internal errors are only returned as HTTP 500 with `{ "success": false }` (no error details to the client).
  - All error details (message, trace) are logged internally via LoggerService and error_log.
- All JSON responses are now generated via a shared static helper: `App\Helpers\ResponseHelper::jsonResponse()`.


