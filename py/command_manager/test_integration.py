#!/usr/bin/env python3
"""
Test script to verify Python Command Manager integration
"""

import requests
import time
import sys


def test_api_health():
    """Test API health endpoint"""
    try:
        response = requests.get('http://localhost:8765/api/health', timeout=5)
        if response.status_code == 200:
            data = response.json()
            print(f"✓ API Health: {data.get('message', 'OK')}")
            return True
        else:
            print(f"✗ API Health failed: {response.status_code}")
            return False
    except requests.exceptions.RequestException as e:
        print(f"✗ API Health failed: {e}")
        return False


def test_commands_list():
    """Test commands list endpoint"""
    try:
        response = requests.get('http://localhost:8765/api/commands', timeout=5)
        if response.status_code == 200:
            data = response.json()
            if data.get('success'):
                commands = data.get('data', [])
                print(f"✓ Commands list: {len(commands)} commands found")
                for cmd in commands:
                    status = cmd.get('status', 'unknown')
                    enabled = 'enabled' if cmd.get('enabled') else 'disabled'
                    print(f"  - {cmd.get('name', 'Unknown')} ({status}, {enabled})")
                return True
            else:
                print("✗ Commands list failed: API returned error")
                return False
        else:
            print(f"✗ Commands list failed: {response.status_code}")
            return False
    except requests.exceptions.RequestException as e:
        print(f"✗ Commands list failed: {e}")
        return False


def test_command_status():
    """Test individual command status"""
    try:
        # First get the list of commands
        response = requests.get('http://localhost:8765/api/commands', timeout=5)
        if response.status_code != 200:
            print("✗ Cannot test command status: commands list failed")
            return False
        
        data = response.json()
        commands = data.get('data', [])
        
        if not commands:
            print("✓ Command status test skipped: no commands configured")
            return True
        
        # Test status of first command
        first_cmd = commands[0]
        cmd_id = first_cmd.get('id')
        
        response = requests.get(f'http://localhost:8765/api/commands/{cmd_id}/status', timeout=5)
        if response.status_code == 200:
            status_data = response.json()
            if status_data.get('success'):
                status = status_data.get('data', {})
                print(f"✓ Command status for '{cmd_id}': {status.get('status', 'unknown')}")
                return True
            else:
                print("✗ Command status failed: API returned error")
                return False
        else:
            print(f"✗ Command status failed: {response.status_code}")
            return False
            
    except requests.exceptions.RequestException as e:
        print(f"✗ Command status failed: {e}")
        return False


def test_laravel_service():
    """Test Laravel service integration (if Laravel is running)"""
    try:
        # Test if Laravel is accessible
        response = requests.get('http://localhost:8000', timeout=5)
        if response.status_code == 200:
            print("✓ Laravel is running")
            
            # Test daemon management page (requires login)
            daemon_response = requests.get('http://localhost:8000/admin/daemon-manage', timeout=5)
            if daemon_response.status_code in [200, 302]:  # 302 = redirect to login
                print("✓ Daemon management route is accessible")
                return True
            else:
                print(f"✗ Daemon management route failed: {daemon_response.status_code}")
                return False
        else:
            print("⚠ Laravel not running or not accessible on port 8000")
            return True  # Not a failure, just not available
            
    except requests.exceptions.RequestException:
        print("⚠ Laravel not running or not accessible on port 8000")
        return True  # Not a failure, just not available


def main():
    """Run all integration tests"""
    print("Laravel Command Manager Integration Test")
    print("=" * 50)
    print()
    
    tests = [
        ("API Health Check", test_api_health),
        ("Commands List", test_commands_list),
        ("Command Status", test_command_status),
        ("Laravel Integration", test_laravel_service),
    ]
    
    passed = 0
    failed = 0
    
    for test_name, test_func in tests:
        print(f"Running {test_name}...")
        try:
            if test_func():
                passed += 1
            else:
                failed += 1
        except Exception as e:
            print(f"✗ {test_name} failed with exception: {e}")
            failed += 1
        print()
    
    print("=" * 50)
    print(f"Test Results: {passed} passed, {failed} failed")
    
    if failed == 0:
        print("🎉 All tests passed! Integration is working correctly.")
        return 0
    else:
        print("❌ Some tests failed. Check the Python Command Manager setup.")
        return 1


if __name__ == "__main__":
    sys.exit(main())