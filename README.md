# Steam Market REST API

A PHP REST API for retrieving Steam Market data using the `allyans3/steam-market-api-v2` library.

## 🚀 Features

- **Steam Market Data**: Prices, search functionality, popular items
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
