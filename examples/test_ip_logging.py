#!/usr/bin/env python3
"""
IP-Logging Tester for the Steam Market REST API
Tests various endpoints to demonstrate IP logging functionality
"""

import requests
import json
import time
import os
from typing import Dict

def test_ip_logging():
    """Tests the IP logging system"""
    
    base_url = "http://localhost:8000"
    
    print("🧪 Testing IP Logging System...")
    print("=" * 50)
    
    # Test endpoints to generate logs
    test_endpoints = [
        "/",
        "/api/v1/test",
        "/api/v1/steam/status", 
        "/api/v1/steam/apps",
        "/api/v1/steam/search?q=AK-47&count=3",
        "/api/v1/nonexistent",  # 404 Error
    ]
    
    print("📡 Generating test requests...")
    for endpoint in test_endpoints:
        try:
            url = base_url + endpoint
            print(f"   → {endpoint}")
            response = requests.get(url, timeout=10)
            print(f"      Status: {response.status_code}")
        except Exception as e:
            print(f"      Error: {str(e)}")
        
        time.sleep(0.5)  # Short pause between requests
    
    print("\n" + "=" * 50)
    time.sleep(2)  # Wait for logs to be written
    
    print("✅ IP Logging test completed!")
    print("\n📝 Log files are stored in the logs/ directory:")
    print("   • logs/access-YYYY-MM-DD.log - IP Access Logs")
    print("   • logs/app-YYYY-MM-DD.log - Application Logs")

def simulate_traffic():
    """Simulates various traffic patterns"""
    
    base_url = "http://localhost:8000"
    
    print("\n🚦 Simulating various traffic patterns...")
    
    # Simulate normal traffic
    print("   Normal traffic...")
    for i in range(5):
        requests.get(f"{base_url}/api/v1/steam/status")
        time.sleep(0.2)
    
    # Simulate search requests
    print("   Search requests...")
    search_terms = ["AK-47", "AWP", "Knife", "Gloves"]
    for term in search_terms:
        requests.get(f"{base_url}/api/v1/steam/search?q={term}")
        time.sleep(0.3)
    
    # Simulate 404 errors
    print("   404 errors...")
    for i in range(3):
        requests.get(f"{base_url}/api/v1/nonexistent-{i}")
        time.sleep(0.1)
    
    print("   Traffic simulation completed!")

def check_log_files():
    """Checks if log files exist and shows basic info"""
    
    print("\n� Checking log files...")
    log_dir = "../logs"  # Relative to examples folder
    
    if not os.path.exists(log_dir):
        print(f"❌ Log directory not found: {log_dir}")
        return
    
    log_files = []
    for file in os.listdir(log_dir):
        if file.endswith('.log'):
            file_path = os.path.join(log_dir, file)
            file_size = os.path.getsize(file_path)
            with open(file_path, 'r') as f:
                line_count = sum(1 for line in f)
            log_files.append({
                'name': file,
                'size': file_size,
                'lines': line_count
            })
    
    if log_files:
        print("📊 Log file statistics:")
        for log_file in log_files:
            print(f"   • {log_file['name']}: {log_file['lines']} lines, {log_file['size']} bytes")
    else:
        print("❌ No log files found")

if __name__ == "__main__":
    # Basic test
    test_ip_logging()
    
    # Traffic simulation
    simulate_traffic()
    
    # Check log files
    check_log_files()
    
    print(f"\n💡 To view logs manually:")
    print(f"   • Check files in the logs/ directory")
    print(f"   • Access logs: logs/access-YYYY-MM-DD.log")
    print(f"   • Application logs: logs/app-YYYY-MM-DD.log")
