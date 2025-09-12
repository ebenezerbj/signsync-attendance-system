package com.signsync.wearos;

import android.Manifest;
import android.app.Service;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.bluetooth.BluetoothManager;
import android.bluetooth.le.BluetoothLeScanner;
import android.bluetooth.le.ScanCallback;
import android.bluetooth.le.ScanFilter;
import android.bluetooth.le.ScanResult;
import android.bluetooth.le.ScanSettings;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.location.Location;
import android.location.LocationListener;
import android.location.LocationManager;
import android.net.wifi.WifiInfo;
import android.net.wifi.WifiManager;
import android.os.Bundle;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;
import android.util.Log;
import androidx.annotation.Nullable;
import androidx.core.app.ActivityCompat;
import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

public class LocationService extends Service implements LocationListener {
    private static final String TAG = "LocationService";
    private static final String PREFS_NAME = "WearOSConfig";
    
    // Location update intervals
    private static final long GPS_UPDATE_INTERVAL = 30000; // 30 seconds
    private static final long WIFI_SCAN_INTERVAL = 60000; // 1 minute
    private static final long BEACON_SCAN_INTERVAL = 15000; // 15 seconds
    
    // Location accuracy thresholds
    private static final float WORKPLACE_RADIUS = 100.0f; // 100 meters
    private static final int MIN_RSSI_THRESHOLD = -80; // WiFi signal strength threshold
    
    // Service components
    private LocationManager locationManager;
    private WifiManager wifiManager;
    private BluetoothManager bluetoothManager;
    private BluetoothAdapter bluetoothAdapter;
    private BluetoothLeScanner bluetoothLeScanner;
    
    // Current location data
    private Location currentLocation;
    private List<android.net.wifi.ScanResult> nearbyWifiNetworks;
    private List<BeaconInfo> nearbyBeacons;
    private Map<String, Integer> knownWifiNetworks;
    private List<String> authorizedBeacons;
    
    // Handlers and preferences
    private Handler locationHandler;
    private SharedPreferences sharedPrefs;
    private boolean isLocationServiceRunning = false;
    
    // Location data class
    public static class LocationData {
        public double latitude;
        public double longitude;
        public float accuracy;
        public long timestamp;
        public List<WifiNetworkInfo> wifiNetworks;
        public List<BeaconInfo> beacons;
        public boolean isAtWorkplace;
        public String locationMethod; // "gps", "wifi", "beacon", "hybrid"
        
        public LocationData() {
            this.wifiNetworks = new ArrayList<>();
            this.beacons = new ArrayList<>();
            this.timestamp = System.currentTimeMillis();
        }
    }
    
    // WiFi network info class
    public static class WifiNetworkInfo {
        public String ssid;
        public String bssid;
        public int rssi;
        public String capabilities;
        public int frequency;
    }
    
    // Beacon info class
    public static class BeaconInfo {
        public String uuid;
        public int major;
        public int minor;
        public int rssi;
        public double distance;
        public String name;
    }

    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "LocationService created");
        
        // Initialize components
        locationManager = (LocationManager) getSystemService(Context.LOCATION_SERVICE);
        wifiManager = (WifiManager) getApplicationContext().getSystemService(Context.WIFI_SERVICE);
        bluetoothManager = (BluetoothManager) getSystemService(Context.BLUETOOTH_SERVICE);
        bluetoothAdapter = bluetoothManager.getAdapter();
        
        if (bluetoothAdapter != null) {
            bluetoothLeScanner = bluetoothAdapter.getBluetoothLeScanner();
        }
        
        sharedPrefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        locationHandler = new Handler(Looper.getMainLooper());
        
        // Initialize data structures
        nearbyWifiNetworks = new ArrayList<>();
        nearbyBeacons = new ArrayList<>();
        knownWifiNetworks = new HashMap<>();
        authorizedBeacons = new ArrayList<>();
        
        // Load configuration
        loadLocationConfiguration();
        
        // Register WiFi scan receiver
        registerReceiver(wifiScanReceiver, new IntentFilter(WifiManager.SCAN_RESULTS_AVAILABLE_ACTION));
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.d(TAG, "LocationService started");
        
        if (!isLocationServiceRunning) {
            isLocationServiceRunning = true;
            startLocationTracking();
        }
        
        return START_STICKY;
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        Log.d(TAG, "LocationService destroyed");
        
        isLocationServiceRunning = false;
        stopLocationTracking();
        
        try {
            unregisterReceiver(wifiScanReceiver);
        } catch (Exception e) {
            Log.w(TAG, "Error unregistering WiFi receiver", e);
        }
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null; // Not a bound service
    }

    private void startLocationTracking() {
        Log.d(TAG, "Starting location tracking");
        
        // Check permissions
        if (!hasLocationPermissions()) {
            Log.e(TAG, "Location permissions not granted");
            return;
        }
        
        // Start GPS tracking
        startGPSTracking();
        
        // Start WiFi scanning
        startWiFiScanning();
        
        // Start beacon scanning
        startBeaconScanning();
    }

    private void stopLocationTracking() {
        Log.d(TAG, "Stopping location tracking");
        
        // Stop GPS
        if (locationManager != null) {
            locationManager.removeUpdates(this);
        }
        
        // Stop WiFi scanning
        locationHandler.removeCallbacks(wifiScanRunnable);
        
        // Stop beacon scanning
        if (bluetoothLeScanner != null) {
            try {
                bluetoothLeScanner.stopScan(beaconScanCallback);
            } catch (Exception e) {
                Log.w(TAG, "Error stopping beacon scan", e);
            }
        }
    }

    private boolean hasLocationPermissions() {
        return ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED &&
               ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED;
    }

    private void startGPSTracking() {
        if (!hasLocationPermissions()) return;
        
        try {
            // Request location updates from GPS
            if (locationManager.isProviderEnabled(LocationManager.GPS_PROVIDER)) {
                locationManager.requestLocationUpdates(
                    LocationManager.GPS_PROVIDER,
                    GPS_UPDATE_INTERVAL,
                    5.0f, // 5 meter minimum distance
                    this
                );
                Log.d(TAG, "GPS tracking started");
            }
            
            // Also request from network provider as backup
            if (locationManager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)) {
                locationManager.requestLocationUpdates(
                    LocationManager.NETWORK_PROVIDER,
                    GPS_UPDATE_INTERVAL,
                    10.0f, // 10 meter minimum distance
                    this
                );
                Log.d(TAG, "Network location tracking started");
            }
            
        } catch (Exception e) {
            Log.e(TAG, "Error starting GPS tracking", e);
        }
    }

    private void startWiFiScanning() {
        locationHandler.post(wifiScanRunnable);
    }

    private final Runnable wifiScanRunnable = new Runnable() {
        @Override
        public void run() {
            if (isLocationServiceRunning) {
                scanWiFiNetworks();
                locationHandler.postDelayed(this, WIFI_SCAN_INTERVAL);
            }
        }
    };

    private void scanWiFiNetworks() {
        if (!wifiManager.isWifiEnabled()) {
            Log.d(TAG, "WiFi not enabled, skipping scan");
            return;
        }
        
        try {
            boolean scanStarted = wifiManager.startScan();
            if (scanStarted) {
                Log.d(TAG, "WiFi scan initiated");
            } else {
                Log.w(TAG, "Failed to start WiFi scan");
            }
        } catch (Exception e) {
            Log.e(TAG, "Error starting WiFi scan", e);
        }
    }

    private final BroadcastReceiver wifiScanReceiver = new BroadcastReceiver() {
        @Override
        public void onReceive(Context context, Intent intent) {
            if (WifiManager.SCAN_RESULTS_AVAILABLE_ACTION.equals(intent.getAction())) {
                List<android.net.wifi.ScanResult> scanResults = wifiManager.getScanResults();
                if (scanResults != null) {
                    nearbyWifiNetworks = scanResults;
                    Log.d(TAG, "WiFi scan completed: " + scanResults.size() + " networks found");
                    processWiFiResults(scanResults);
                }
            }
        }
    };

    private void processWiFiResults(List<android.net.wifi.ScanResult> scanResults) {
        for (android.net.wifi.ScanResult result : scanResults) {
            if (result.level > MIN_RSSI_THRESHOLD) {
                // Check if this is a known workplace WiFi network
                if (knownWifiNetworks.containsKey(result.BSSID)) {
                    Log.d(TAG, "Detected workplace WiFi: " + result.SSID + " (" + result.level + " dBm)");
                    // Could trigger workplace detection logic here
                }
            }
        }
    }

    private void startBeaconScanning() {
        if (bluetoothAdapter == null || !bluetoothAdapter.isEnabled()) {
            Log.d(TAG, "Bluetooth not available or enabled");
            return;
        }
        
        if (bluetoothLeScanner == null) {
            Log.e(TAG, "Bluetooth LE scanner not available");
            return;
        }
        
        try {
            ScanSettings settings = new ScanSettings.Builder()
                .setScanMode(ScanSettings.SCAN_MODE_LOW_POWER)
                .build();
            
            List<ScanFilter> filters = new ArrayList<>();
            // Add filters for specific beacon types if needed
            
            bluetoothLeScanner.startScan(filters, settings, beaconScanCallback);
            Log.d(TAG, "Beacon scanning started");
            
            // Schedule periodic beacon processing
            locationHandler.postDelayed(beaconProcessingRunnable, BEACON_SCAN_INTERVAL);
            
        } catch (Exception e) {
            Log.e(TAG, "Error starting beacon scan", e);
        }
    }

    private final ScanCallback beaconScanCallback = new ScanCallback() {
        @Override
        public void onScanResult(int callbackType, ScanResult result) {
            processBeaconResult(result);
        }

        @Override
        public void onBatchScanResults(List<ScanResult> results) {
            for (ScanResult result : results) {
                processBeaconResult(result);
            }
        }

        @Override
        public void onScanFailed(int errorCode) {
            Log.e(TAG, "Beacon scan failed with error: " + errorCode);
        }
    };

    private void processBeaconResult(ScanResult result) {
        BluetoothDevice device = result.getDevice();
        byte[] scanRecord = result.getScanRecord() != null ? result.getScanRecord().getBytes() : null;
        
        if (scanRecord != null && isIBeacon(scanRecord)) {
            BeaconInfo beacon = parseIBeacon(scanRecord, result.getRssi());
            if (beacon != null) {
                // Check if this is an authorized workplace beacon
                if (authorizedBeacons.contains(beacon.uuid)) {
                    Log.d(TAG, "Detected workplace beacon: " + beacon.uuid + " (RSSI: " + beacon.rssi + ")");
                    updateBeaconList(beacon);
                }
            }
        }
    }

    private final Runnable beaconProcessingRunnable = new Runnable() {
        @Override
        public void run() {
            if (isLocationServiceRunning) {
                // Process and clean up old beacon data
                cleanupOldBeacons();
                locationHandler.postDelayed(this, BEACON_SCAN_INTERVAL);
            }
        }
    };

    private boolean isIBeacon(byte[] scanRecord) {
        // Simple iBeacon detection - check for Apple's iBeacon prefix
        if (scanRecord.length >= 25) {
            return scanRecord[5] == (byte) 0x4c && scanRecord[6] == (byte) 0x00 &&
                   scanRecord[7] == (byte) 0x02 && scanRecord[8] == (byte) 0x15;
        }
        return false;
    }

    private BeaconInfo parseIBeacon(byte[] scanRecord, int rssi) {
        if (scanRecord.length < 25) return null;
        
        BeaconInfo beacon = new BeaconInfo();
        
        // Extract UUID (16 bytes starting at index 9)
        StringBuilder uuidBuilder = new StringBuilder();
        for (int i = 9; i < 25; i++) {
            uuidBuilder.append(String.format("%02x", scanRecord[i] & 0xff));
            if (i == 12 || i == 14 || i == 16 || i == 18) {
                uuidBuilder.append("-");
            }
        }
        beacon.uuid = uuidBuilder.toString().toUpperCase();
        
        // Extract Major (2 bytes)
        beacon.major = ((scanRecord[25] & 0xff) << 8) | (scanRecord[26] & 0xff);
        
        // Extract Minor (2 bytes)
        beacon.minor = ((scanRecord[27] & 0xff) << 8) | (scanRecord[28] & 0xff);
        
        beacon.rssi = rssi;
        beacon.distance = calculateDistance(rssi, -59); // Assuming -59 dBm at 1 meter
        
        return beacon;
    }

    private double calculateDistance(int rssi, int txPower) {
        if (rssi == 0) return -1.0;
        
        double ratio = (double) (txPower - rssi) / 20.0;
        return Math.pow(10, ratio);
    }

    private void updateBeaconList(BeaconInfo newBeacon) {
        // Remove old entry for same beacon
        nearbyBeacons.removeIf(beacon -> 
            beacon.uuid.equals(newBeacon.uuid) && 
            beacon.major == newBeacon.major && 
            beacon.minor == newBeacon.minor);
        
        // Add new entry
        nearbyBeacons.add(newBeacon);
    }

    private void cleanupOldBeacons() {
        long currentTime = System.currentTimeMillis();
        nearbyBeacons.removeIf(beacon -> 
            (currentTime - beacon.distance) > 30000); // Remove beacons not seen for 30 seconds
    }

    // LocationListener implementation
    @Override
    public void onLocationChanged(Location location) {
        currentLocation = location;
        Log.d(TAG, "Location updated: " + location.getLatitude() + ", " + location.getLongitude() + 
              " (accuracy: " + location.getAccuracy() + "m)");
        
        // Check if at workplace
        boolean atWorkplace = isAtWorkplace(location);
        Log.d(TAG, "At workplace: " + atWorkplace);
    }

    @Override
    public void onStatusChanged(String provider, int status, Bundle extras) {
        Log.d(TAG, "Location provider " + provider + " status changed: " + status);
    }

    @Override
    public void onProviderEnabled(String provider) {
        Log.d(TAG, "Location provider enabled: " + provider);
    }

    @Override
    public void onProviderDisabled(String provider) {
        Log.d(TAG, "Location provider disabled: " + provider);
    }

    private boolean isAtWorkplace(Location location) {
        // Get workplace coordinates from configuration
        float workplaceLat = sharedPrefs.getFloat("workplace_lat", 0.0f);
        float workplaceLng = sharedPrefs.getFloat("workplace_lng", 0.0f);
        
        if (workplaceLat == 0.0f && workplaceLng == 0.0f) {
            return false; // No workplace location configured
        }
        
        Location workplaceLocation = new Location("workplace");
        workplaceLocation.setLatitude(workplaceLat);
        workplaceLocation.setLongitude(workplaceLng);
        
        float distance = location.distanceTo(workplaceLocation);
        return distance <= WORKPLACE_RADIUS;
    }

    private void loadLocationConfiguration() {
        // Load known WiFi networks from preferences
        try {
            String wifiNetworksJson = sharedPrefs.getString("known_wifi_networks", "{}");
            JSONObject wifiNetworks = new JSONObject(wifiNetworksJson);
            
            knownWifiNetworks.clear();
            for (java.util.Iterator<String> keys = wifiNetworks.keys(); keys.hasNext();) {
                String bssid = keys.next();
                knownWifiNetworks.put(bssid, wifiNetworks.getInt(bssid));
            }
            
        } catch (JSONException e) {
            Log.e(TAG, "Error loading WiFi networks configuration", e);
        }
        
        // Load authorized beacons from preferences
        try {
            String beaconsJson = sharedPrefs.getString("authorized_beacons", "[]");
            JSONArray beacons = new JSONArray(beaconsJson);
            
            authorizedBeacons.clear();
            for (int i = 0; i < beacons.length(); i++) {
                authorizedBeacons.add(beacons.getString(i));
            }
            
        } catch (JSONException e) {
            Log.e(TAG, "Error loading beacons configuration", e);
        }
    }

    // Public method to get current location data
    public LocationData getCurrentLocationData() {
        LocationData locationData = new LocationData();
        
        if (currentLocation != null) {
            locationData.latitude = currentLocation.getLatitude();
            locationData.longitude = currentLocation.getLongitude();
            locationData.accuracy = currentLocation.getAccuracy();
            locationData.isAtWorkplace = isAtWorkplace(currentLocation);
            locationData.locationMethod = "gps";
        }
        
        // Add WiFi network information
        for (android.net.wifi.ScanResult wifiResult : nearbyWifiNetworks) {
            WifiNetworkInfo wifiInfo = new WifiNetworkInfo();
            wifiInfo.ssid = wifiResult.SSID;
            wifiInfo.bssid = wifiResult.BSSID;
            wifiInfo.rssi = wifiResult.level;
            wifiInfo.capabilities = wifiResult.capabilities;
            wifiInfo.frequency = wifiResult.frequency;
            locationData.wifiNetworks.add(wifiInfo);
        }
        
        // Add beacon information
        locationData.beacons.addAll(nearbyBeacons);
        
        // Determine location method based on available data
        if (currentLocation != null && !locationData.wifiNetworks.isEmpty() && !locationData.beacons.isEmpty()) {
            locationData.locationMethod = "hybrid";
        } else if (!locationData.wifiNetworks.isEmpty()) {
            locationData.locationMethod = "wifi";
        } else if (!locationData.beacons.isEmpty()) {
            locationData.locationMethod = "beacon";
        }
        
        return locationData;
    }

    // Static method to start location service
    public static void startLocationService(Context context) {
        Intent intent = new Intent(context, LocationService.class);
        context.startService(intent);
    }

    // Static method to stop location service
    public static void stopLocationService(Context context) {
        Intent intent = new Intent(context, LocationService.class);
        context.stopService(intent);
    }
}
