package com.signsync.attendance.service;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.pm.PackageManager;
import android.net.wifi.ScanResult;
import android.net.wifi.WifiManager;
import android.util.Log;
import androidx.core.app.ActivityCompat;
import com.google.gson.Gson;
import java.util.ArrayList;
import java.util.List;

/**
 * WiFi Scanner Service for Office Verification
 * Scans for available WiFi networks to verify employee is in office
 */
public class WiFiScannerService {
    private static final String TAG = "WiFiScannerService";
    
    private Context context;
    private WifiManager wifiManager;
    private List<WiFiNetwork> detectedNetworks;
    private WiFiScanListener listener;
    private boolean isScanning = false;
    
    public interface WiFiScanListener {
        void onWiFiScanCompleted(List<WiFiNetwork> networks, String networksJson);
        void onWiFiScanFailed(String error);
    }
    
    public static class WiFiNetwork {
        public String ssid;
        public String bssid;
        public int rssi;
        public String capabilities;
        public int frequency;
        
        public WiFiNetwork(String ssid, String bssid, int rssi, String capabilities, int frequency) {
            this.ssid = ssid;
            this.bssid = bssid;
            this.rssi = rssi;
            this.capabilities = capabilities;
            this.frequency = frequency;
        }
    }
    
    private BroadcastReceiver wifiScanReceiver = new BroadcastReceiver() {
        @Override
        public void onReceive(Context context, Intent intent) {
            String action = intent.getAction();
            if (WifiManager.SCAN_RESULTS_AVAILABLE_ACTION.equals(action)) {
                boolean success = intent.getBooleanExtra(WifiManager.EXTRA_RESULTS_UPDATED, false);
                if (success) {
                    scanSuccess();
                } else {
                    scanFailure();
                }
            }
        }
    };
    
    public WiFiScannerService(Context context) {
        this.context = context;
        this.wifiManager = (WifiManager) context.getApplicationContext().getSystemService(Context.WIFI_SERVICE);
        this.detectedNetworks = new ArrayList<>();
    }
    
    public void setWiFiScanListener(WiFiScanListener listener) {
        this.listener = listener;
    }
    
    public void startWiFiScan() {
        if (wifiManager == null) {
            if (listener != null) {
                listener.onWiFiScanFailed("WiFi manager not available");
            }
            return;
        }
        
        if (!wifiManager.isWifiEnabled()) {
            if (listener != null) {
                listener.onWiFiScanFailed("WiFi is disabled");
            }
            return;
        }
        
        // Check permissions
        if (ActivityCompat.checkSelfPermission(context, android.Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
            if (listener != null) {
                listener.onWiFiScanFailed("Location permission required for WiFi scanning");
            }
            return;
        }
        
        if (isScanning) {
            Log.w(TAG, "WiFi scan already in progress");
            return;
        }
        
        isScanning = true;
        
        // Register receiver for scan results
        IntentFilter intentFilter = new IntentFilter();
        intentFilter.addAction(WifiManager.SCAN_RESULTS_AVAILABLE_ACTION);
        context.registerReceiver(wifiScanReceiver, intentFilter);
        
        // Start scan
        boolean success = wifiManager.startScan();
        if (!success) {
            isScanning = false;
            context.unregisterReceiver(wifiScanReceiver);
            if (listener != null) {
                listener.onWiFiScanFailed("Failed to start WiFi scan");
            }
        } else {
            Log.d(TAG, "WiFi scan started successfully");
        }
    }
    
    private void scanSuccess() {
        isScanning = false;
        
        try {
            context.unregisterReceiver(wifiScanReceiver);
        } catch (IllegalArgumentException e) {
            // Receiver not registered
        }
        
        if (ActivityCompat.checkSelfPermission(context, android.Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
            if (listener != null) {
                listener.onWiFiScanFailed("Location permission required");
            }
            return;
        }
        
        List<ScanResult> results = wifiManager.getScanResults();
        detectedNetworks.clear();
        
        for (ScanResult result : results) {
            // Filter out hidden networks and add valid ones
            if (result.SSID != null && !result.SSID.isEmpty()) {
                WiFiNetwork network = new WiFiNetwork(
                    result.SSID,
                    result.BSSID,
                    result.level,
                    result.capabilities,
                    result.frequency
                );
                detectedNetworks.add(network);
            }
        }
        
        Log.d(TAG, "WiFi scan completed. Found " + detectedNetworks.size() + " networks");
        
        // Convert to JSON for API
        Gson gson = new Gson();
        String networksJson = gson.toJson(detectedNetworks);
        
        if (listener != null) {
            listener.onWiFiScanCompleted(detectedNetworks, networksJson);
        }
    }
    
    private void scanFailure() {
        isScanning = false;
        
        try {
            context.unregisterReceiver(wifiScanReceiver);
        } catch (IllegalArgumentException e) {
            // Receiver not registered
        }
        
        Log.e(TAG, "WiFi scan failed");
        if (listener != null) {
            listener.onWiFiScanFailed("WiFi scan failed");
        }
    }
    
    public List<WiFiNetwork> getDetectedNetworks() {
        return detectedNetworks;
    }
    
    public String getDetectedNetworksJson() {
        Gson gson = new Gson();
        return gson.toJson(detectedNetworks);
    }
    
    public void cleanup() {
        try {
            if (isScanning) {
                context.unregisterReceiver(wifiScanReceiver);
            }
        } catch (IllegalArgumentException e) {
            // Receiver not registered
        }
        isScanning = false;
    }
}
