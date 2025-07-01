## ⚠️ Disclaimer: 
```diff
-This is an unofficial third-party API and is not affiliated with or endorsed by Valve Corporation or Steam.
-All Steam-related trademarks and data belong to their respective owners.
```

A PHP REST API for retrieving Steam Market data with comprehensive logging and CORS support.

# Steam Market REST API

A PHP REST API for retrieving Steam Market data using the `allyans3/steam-market-api-v2` library.

## 🚀 Features

- **Steam Market Data**: Prices, search functionality, popular items
- **Steam Profile Data**: Profile info, inventory, friends, recent games
- **Item Images**: Automatic item image URLs for all endpoints
- **Multiple Steam Apps**: CS:GO, Dota 2, TF2, Rust, Unturned
- **App Search**: Dynamic Steam app search by name
- **REST API**: Clean JSON endpoints for Python/JavaScript clients
- **Error Handling**: Robust error handling and logging
- **IP Logging**: Complete request logging with IP tracking
- **Admin Endpoints**: View access logs and log statistics
- **Documentation**: Integrated API documentation

## 📋 Prerequisites

- PHP 7.4+ or 8.0+
- Composer

## 🛠️ Installation

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

**Endpoints that require API key:**
- Friends list (`/api/v1/steam/profile/{id}/friends`)
- Recently played games (`/api/v1/steam/profile/{id}/games/recent`)
- Vanity URL resolution (fallback to community XML without key)

**Endpoints that work without API key:**
- Profile info (limited data via community XML)
- Inventory (community endpoint)
- Steam64 ID profile lookup

## 📡 API Endpoints

### Base Endpoints
- `GET /` - API Info
- `GET /api/v1/test` - API Test
- `GET /api/v1/docs` - Complete Documentation

### Steam Market Endpoints
- `GET /api/v1/steam/status` - API Status
- `GET /api/v1/steam/apps` - Supported Steam Apps
- `GET /api/v1/steam/find-app?name={app_name}` - Search app by name
- `GET /api/v1/steam/app/{appId}` - Get app details
- `GET /api/v1/steam/item/{itemName}` - Get item price
- `GET /api/v1/steam/search?q={query}` - Search items
- `GET /api/v1/steam/popular` - Popular items

### Steam Profile Endpoints
- `GET /api/v1/steam/profile/{identifier}` - Get Steam profile info
- `GET /api/v1/steam/profile/{identifier}/inventory` - Get user inventory (CS2 default)
- `GET /api/v1/steam/profile/{identifier}/friends` - Get friends list (requires API key)
- `GET /api/v1/steam/profile/{identifier}/games/recent` - Get recently played games (requires API key)
- `GET /api/v1/steam/profile/search` - Profile search (returns 501 - not supported by Steam)

**Note**: `{identifier}` can be:
- Steam64 ID (e.g., `76561198037867621`)
- Vanity URL name (e.g., `StuntmanLT`)
- Full profile URL (e.g., `https://steamcommunity.com/id/StuntmanLT/`)

## 🔍 Examples

### Search App by Name
```bash
curl "http://localhost:8000/api/v1/steam/find-app?name=Counter-Strike"
curl "http://localhost:8000/api/v1/steam/find-app?name=Dota"
```

### Get App Details
```bash
curl "http://localhost:8000/api/v1/steam/app/730"
curl "http://localhost:8000/api/v1/steam/app/570"
```

### Get Item Price
```bash
curl "http://localhost:8000/api/v1/steam/item/AK-47%20|%20Redline%20(Field-Tested)"
```

### Search Items
```bash
curl "http://localhost:8000/api/v1/steam/search?q=AK-47&count=5&app_id=730"
```

### Steam Profile Examples
```bash
# Get profile info (works with Steam64 ID, vanity URL, or profile URL)
curl "http://localhost:8000/api/v1/steam/profile/76561198037867621"
curl "http://localhost:8000/api/v1/steam/profile/StuntmanLT"
curl "http://localhost:8000/api/v1/steam/profile/https://steamcommunity.com/id/StuntmanLT/"

# Get inventory (CS2 by default, add ?appid=570 for Dota 2)
curl "http://localhost:8000/api/v1/steam/profile/76561198037867621/inventory"
curl "http://localhost:8000/api/v1/steam/profile/StuntmanLT/inventory?appid=570"

# Get friends list (requires STEAM_API_KEY)
curl "http://localhost:8000/api/v1/steam/profile/76561198037867621/friends"

# Get recent games (requires STEAM_API_KEY)
curl "http://localhost:8000/api/v1/steam/profile/76561198037867621/games/recent"
```

### Python Example
```python
import requests

# API Base URL
api_url = "http://localhost:8000/api/v1"

# Get item price
response = requests.get(f"{api_url}/steam/item/AK-47 | Redline (Field-Tested)")
data = response.json()
print(f"Price: {data['lowest_price']}")
print(f"Image: {data['image_url']}")

# Search items
search_response = requests.get(f"{api_url}/steam/search", params={
    "q": "AK-47",
    "count": 5
})
items = search_response.json()['items']
for item in items:
    print(f"{item['name']}: {item['sell_price']} - {item['image_url']}")

# Steam Profile Examples
# Get profile info
profile_response = requests.get(f"{api_url}/steam/profile/76561198037867621")
profile_data = profile_response.json()
print(f"Player: {profile_data['profile']['personaname']}")
print(f"Avatar: {profile_data['profile']['avatarfull']}")

# Get inventory
inventory_response = requests.get(f"{api_url}/steam/profile/76561198037867621/inventory")
inventory_data = inventory_response.json()
print(f"Items in inventory: {len(inventory_data['inventory']['items'])}")

# Get friends list (requires API key)
friends_response = requests.get(f"{api_url}/steam/profile/76561198037867621/friends")
friends_data = friends_response.json()
print(f"Friends count: {friends_data['friends']['friends_count']}")
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

## 📄 License

[![Creative Commons License](https://i.creativecommons.org/l/by-nc-sa/4.0/88x31.png)](http://creativecommons.org/licenses/by-nc-sa/4.0/)

This work is licensed under a [Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International License](http://creativecommons.org/licenses/by-nc-sa/4.0/).

### What does this mean?

**✅ You are free to:**
- **Share** — copy and redistribute the material in any medium or format
- **Adapt** — remix, transform, and build upon the material

**⚠️ Under the following terms:**
- **Attribution** — You must give appropriate credit, provide a link to the license, and indicate if changes were made
- **NonCommercial** — You may not use the material for commercial purposes
- **ShareAlike** — If you remix, transform, or build upon the material, you must distribute your contributions under the same license as the original

### Notes on Commercial Use

**For Commercial Use:** This project may not be used commercially.

**What counts as commercial:**
- Selling the software or services
- Use in commercial products
- Monetizing services that use this API
- Any direct or indirect profit generation

**Non-commercial use includes:**
- Personal projects
- Educational purposes
- Open-source projects without profit intent
- Research and development

## 🙏 Used Libraries

- **allyans3/steam-market-api-v2** - MIT License
- **slim/slim** - MIT License
- **slim/psr7** - MIT License  
- **monolog/monolog** - MIT License
- **vlucas/phpdotenv** - BSD-3-Clause License
- **php-di/php-di** - MIT License
- **phpunit/phpunit** (dev) - BSD-3-Clause License

All used libraries are available under very liberal open-source licenses and permit use in this project.
