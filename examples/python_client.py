"""
Steam Market REST API Python Client
Beispiel-Client für die PHP Steam Market REST API
"""

import requests
import json
from typing import Dict, List, Optional
from urllib.parse import quote


class SteamMarketApiClient:
    """Python Client für die Steam Market REST API"""
    
    def __init__(self, base_url: str = "http://localhost:8000"):
        """
        Initialisiert den API Client
        
        Args:
            base_url: Basis-URL der API (Standard: http://localhost:8000)
        """
        self.base_url = base_url.rstrip('/')
        self.api_base = f"{self.base_url}/api/v1"
        
    def _make_request(self, endpoint: str, params: Optional[Dict] = None) -> Dict:
        """
        Macht einen HTTP-Request zur API
        
        Args:
            endpoint: API Endpoint
            params: URL Parameter
            
        Returns:
            JSON Response als Dictionary
        """
        url = f"{self.api_base}{endpoint}"
        
        try:
            response = requests.get(url, params=params, timeout=30)
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            return {
                'success': False,
                'error': f"Request failed: {str(e)}",
                'endpoint': endpoint
            }
    
    def test_connection(self) -> Dict:
        """Testet die Verbindung zur API"""
        return self._make_request('/test')
    
    def get_status(self) -> Dict:
        """Holt den Status der Steam API"""
        return self._make_request('/steam/status')
    
    def find_app_by_name(self, app_name: str) -> Dict:
        """
        Sucht Steam Apps anhand des Namens
        
        Args:
            app_name: Name der zu suchenden App
            
        Returns:
            Liste der gefundenen Apps
        """
        params = {'name': app_name}
        return self._make_request('/steam/find-app', params)
    
    def get_app_details(self, app_id: int) -> Dict:
        """
        Holt detaillierte Informationen zu einer Steam App
        
        Args:
            app_id: Steam App ID
            
        Returns:
            App-Details mit Market-Support Info
        """
        return self._make_request(f'/steam/app/{app_id}')
    
    def get_supported_apps(self) -> Dict:
        """Holt die Liste der unterstützten Steam Apps"""
        return self._make_request('/steam/apps')
    
    def get_item_price(self, item_name: str, app_id: int = 730) -> Dict:
        """
        Holt den Preis eines bestimmten Items
        
        Args:
            item_name: Name des Items
            app_id: Steam App ID (Standard: 730 = CS:GO)
            
        Returns:
            Item-Daten mit Preisen
        """
        encoded_name = quote(item_name)
        endpoint = f"/steam/item/{encoded_name}"
        params = {'app_id': app_id} if app_id != 730 else None
        
        return self._make_request(endpoint, params)
    
    def search_items(self, query: str, app_id: int = 730, count: int = 10) -> Dict:
        """
        Sucht nach Items basierend auf einem Suchbegriff
        
        Args:
            query: Suchbegriff
            app_id: Steam App ID (Standard: 730 = CS:GO)
            count: Anzahl der Ergebnisse (max: 50)
            
        Returns:
            Liste der gefundenen Items
        """
        params = {
            'q': query,
            'app_id': app_id,
            'count': min(count, 50)
        }
        
        return self._make_request('/steam/search', params)
    
    def get_popular_items(self, app_id: int = 730) -> Dict:
        """
        Holt beliebte Items für eine Steam App
        
        Args:
            app_id: Steam App ID (Standard: 730 = CS:GO)
            
        Returns:
            Liste der beliebten Items
        """
        params = {'app_id': app_id} if app_id != 730 else None
        return self._make_request('/steam/popular', params)


def main():
    """Beispiel-Verwendung des API Clients"""
    
    # Client initialisieren
    client = SteamMarketApiClient()
    
    # Verbindung testen
    print("🔗 Teste API-Verbindung...")
    test_result = client.test_connection()
    if test_result.get('success'):
        print("✅ API ist erreichbar!")
        print(f"   Message: {test_result.get('message')}")
    else:
        print("❌ API nicht erreichbar!")
        return
    
    
    print("\n" + "="*50)
    
    # App-Suche demonstrieren
    print("🔍 Suche Steam Apps...")
    search_result = client.find_app_by_name("Counter-Strike")
    if search_result.get('success'):
        apps = search_result.get('apps', [])
        print(f"✅ Gefunden {len(apps)} Apps für 'Counter-Strike':")
        for app in apps[:3]:  # Nur erste 3 anzeigen
            print(f"   {app.get('id')}: {app.get('name')}")
    else:
        print(f"❌ Fehler bei der App-Suche: {search_result.get('error')}")
    
    print("\n" + "="*50)
    
    # App-Details demonstrieren
    print("📋 Lade App-Details...")
    app_details = client.get_app_details(730)  # CS2
    if app_details.get('success'):
        print(f"✅ App-Details für {app_details.get('name')}:")
        print(f"   Typ: {app_details.get('type')}")
        print(f"   Market Support: {app_details.get('has_market')}")
        print(f"   Entwickler: {', '.join(app_details.get('developers', []))}")
    else:
        print(f"❌ Fehler beim Laden der App-Details: {app_details.get('error')}")
    
    print("\n" + "="*50)
    
    # Unterstützte Apps anzeigen
    print("📱 Lade unterstützte Steam Apps...")
    apps_result = client.get_supported_apps()
    if apps_result.get('success'):
        apps = apps_result.get('apps', {})
        print("✅ Unterstützte Apps:")
        for app_id, app_info in apps.items():
            print(f"   {app_id}: {app_info['name']}")
    
    print("\n" + "="*50)
    
    # Item-Preis abfragen
    print("💰 Lade Item-Preis...")
    item_name = "AK-47 | Redline (Field-Tested)"
    price_result = client.get_item_price(item_name)
    
    if price_result.get('success'):
        print(f"✅ Preis für '{item_name}':")
        print(f"   Niedrigster Preis: {price_result.get('lowest_price', 'N/A')}")
        print(f"   Median Preis: {price_result.get('median_price', 'N/A')}")
        print(f"   Verkaufsvolumen: {price_result.get('volume', 'N/A')}")
        if price_result.get('image_url'):
            print(f"   Bild-URL: {price_result.get('image_url')}")
    else:
        print(f"❌ Fehler beim Laden des Preises: {price_result.get('error')}")
    
    print("\n" + "="*50)
    
    # Items suchen
    print("🔍 Suche nach Items...")
    search_result = client.search_items("AK-47", count=3)
    
    if search_result.get('success'):
        items = search_result.get('items', [])
        print(f"✅ Gefunden {len(items)} Items für 'AK-47':")
        for item in items:
            print(f"   - {item.get('name', 'Unknown')}")
            print(f"     Verkaufspreis: {item.get('sell_price', 'N/A')}")
            print(f"     Listings: {item.get('sell_listings', 'N/A')}")
            if item.get('image_url'):
                print(f"     Bild: {item.get('image_url')}")
    else:
        print(f"❌ Fehler bei der Suche: {search_result.get('error')}")
    
    print("\n" + "="*50)
    
    # Beliebte Items
    print("🔥 Lade beliebte Items...")
    popular_result = client.get_popular_items()
    
    if popular_result.get('success'):
        items = popular_result.get('items', [])
        print(f"✅ {len(items)} beliebte Items:")
        for i, item in enumerate(items[:5], 1):  # Nur erste 5 anzeigen
            print(f"   {i}. {item.get('name', 'Unknown')}")
            print(f"      Preis: {item.get('sell_price_text', 'N/A')}")
            if item.get('image_url'):
                print(f"      Bild: {item.get('image_url')}")
    else:
        print(f"❌ Fehler beim Laden beliebter Items: {popular_result.get('error')}")


if __name__ == "__main__":
    main()
