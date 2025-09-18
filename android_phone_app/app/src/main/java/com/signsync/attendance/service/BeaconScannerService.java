package com.signsync.attendance.service;

import android.Manifest;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.bluetooth.BluetoothManager;
import android.bluetooth.le.BluetoothLeScanner;
import android.bluetooth.le.ScanCallback;
import android.bluetooth.le.ScanResult;
import android.content.Context;
import android.content.pm.PackageManager;
import android.os.Handler;
import android.os.Looper;
import android.util.Log;
import androidx.core.app.ActivityCompat;
import com.google.gson.Gson;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;

/**
 * Bluetooth LE Beacon Scanner for Office Verification
 * Scans for BLE beacons to verify employee is in office
 */
public class BeaconScannerService {
    private static final String TAG = "BeaconScannerService";
    private static final long SCAN_PERIOD = 10000; // 10 seconds
    
    private Context context;
    private BluetoothAdapter bluetoothAdapter;
    private BluetoothLeScanner bluetoothLeScanner;
    private List<BeaconData> detectedBeacons;
    private BeaconScanListener listener;
    private boolean isScanning = false;
    private Handler handler;
    
    public interface BeaconScanListener {
        void onBeaconScanCompleted(List<BeaconData> beacons, String beaconsJson);
        void onBeaconScanFailed(String error);
    }
    
    public static class BeaconData {
        public String uuid;
        public String address;
        public int rssi;
        public String name;
        public double distance;
        public long timestamp;
        
        public BeaconData(String uuid, String address, int rssi, String name, double distance) {
            this.uuid = uuid;
            this.address = address;
            this.rssi = rssi;
            this.name = name;
            this.distance = distance;
            this.timestamp = System.currentTimeMillis();
        }
    }
    
    private ScanCallback leScanCallback = new ScanCallback() {
        @Override
        public void onScanResult(int callbackType, ScanResult result) {
            BluetoothDevice device = result.getDevice();
            
            if (ActivityCompat.checkSelfPermission(context, Manifest.permission.BLUETOOTH_CONNECT) != PackageManager.PERMISSION_GRANTED) {
                return;
            }
            
            String deviceName = device.getName();
            String deviceAddress = device.getAddress();
            int rssi = result.getRssi();
            
            // Try to extract UUID from scan record (iBeacon format)
            String uuid = extractUUIDFromScanRecord(result.getScanRecord().getBytes());
            
            // Calculate approximate distance based on RSSI
            double distance = calculateDistance(rssi, -59); // Assuming -59 dBm at 1 meter
            
            BeaconData beacon = new BeaconData(uuid, deviceAddress, rssi, deviceName, distance);
            
            // Add to list if not already present (avoid duplicates)
            boolean exists = false;
            for (BeaconData existing : detectedBeacons) {
                if (existing.address.equals(deviceAddress)) {
                    // Update with latest data
                    existing.rssi = rssi;
                    existing.distance = distance;
                    existing.timestamp = System.currentTimeMillis();
                    exists = true;
                    break;
                }
            }
            
            if (!exists) {
                detectedBeacons.add(beacon);
                Log.d(TAG, "Beacon detected: " + deviceAddress + " RSSI: " + rssi + " Distance: " + String.format("%.2f", distance) + "m");
            }
        }
        
        @Override
        public void onScanFailed(int errorCode) {
            Log.e(TAG, "BLE scan failed with error: " + errorCode);
            stopBeaconScan();
            if (listener != null) {
                listener.onBeaconScanFailed("BLE scan failed with error: " + errorCode);
            }
        }
    };
    
    public BeaconScannerService(Context context) {
        this.context = context;
        this.detectedBeacons = new ArrayList<>();
        this.handler = new Handler(Looper.getMainLooper());
        
        BluetoothManager bluetoothManager = (BluetoothManager) context.getSystemService(Context.BLUETOOTH_SERVICE);
        if (bluetoothManager != null) {
            bluetoothAdapter = bluetoothManager.getAdapter();
            if (bluetoothAdapter != null) {
                bluetoothLeScanner = bluetoothAdapter.getBluetoothLeScanner();
            }
        }
    }
    
    public void setBeaconScanListener(BeaconScanListener listener) {
        this.listener = listener;
    }
    
    public void startBeaconScan() {
        if (bluetoothAdapter == null || !bluetoothAdapter.isEnabled()) {
            if (listener != null) {
                listener.onBeaconScanFailed("Bluetooth is not enabled");
            }
            return;
        }
        
        if (bluetoothLeScanner == null) {
            if (listener != null) {
                listener.onBeaconScanFailed("BLE scanner not available");
            }
            return;
        }
        
        // Check permissions
        if (ActivityCompat.checkSelfPermission(context, Manifest.permission.BLUETOOTH_SCAN) != PackageManager.PERMISSION_GRANTED ||
            ActivityCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
            if (listener != null) {
                listener.onBeaconScanFailed("Bluetooth scan and location permissions required");
            }
            return;
        }
        
        if (isScanning) {
            Log.w(TAG, "Beacon scan already in progress");
            return;
        }
        
        detectedBeacons.clear();
        isScanning = true;
        
        // Start scanning
        bluetoothLeScanner.startScan(leScanCallback);
        Log.d(TAG, "BLE beacon scan started");
        
        // Stop scanning after SCAN_PERIOD
        handler.postDelayed(this::stopBeaconScan, SCAN_PERIOD);
    }
    
    public void stopBeaconScan() {
        if (!isScanning) return;
        
        isScanning = false;
        
        if (bluetoothLeScanner != null && ActivityCompat.checkSelfPermission(context, Manifest.permission.BLUETOOTH_SCAN) == PackageManager.PERMISSION_GRANTED) {
            bluetoothLeScanner.stopScan(leScanCallback);
        }
        
        Log.d(TAG, "BLE beacon scan completed. Found " + detectedBeacons.size() + " beacons");
        
        // Convert to JSON for API
        Gson gson = new Gson();
        String beaconsJson = gson.toJson(detectedBeacons);
        
        if (listener != null) {
            listener.onBeaconScanCompleted(detectedBeacons, beaconsJson);
        }
    }
    
    private String extractUUIDFromScanRecord(byte[] scanRecord) {
        if (scanRecord == null || scanRecord.length < 30) {
            return "unknown";
        }
        
        // Look for iBeacon signature (0x02, 0x15)
        for (int i = 0; i < scanRecord.length - 25; i++) {
            if (scanRecord[i] == 0x02 && scanRecord[i + 1] == 0x15) {
                // Extract UUID (16 bytes starting at i+2)
                byte[] uuidBytes = Arrays.copyOfRange(scanRecord, i + 2, i + 18);
                return bytesToUuid(uuidBytes);
            }
        }
        
        return "unknown";
    }
    
    private String bytesToUuid(byte[] bytes) {
        if (bytes.length != 16) return "unknown";
        
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < 16; i++) {
            if (i == 4 || i == 6 || i == 8 || i == 10) {
                sb.append("-");
            }
            sb.append(String.format("%02x", bytes[i] & 0xFF));
        }
        return sb.toString().toUpperCase();
    }
    
    private double calculateDistance(int rssi, int txPower) {
        if (rssi == 0) {
            return -1.0; // Cannot determine distance
        }
        
        double ratio = (double) (txPower - rssi) / 20.0;
        return Math.pow(10, ratio);
    }
    
    public List<BeaconData> getDetectedBeacons() {
        return detectedBeacons;
    }
    
    public String getDetectedBeaconsJson() {
        Gson gson = new Gson();
        return gson.toJson(detectedBeacons);
    }
    
    public void cleanup() {
        if (isScanning) {
            stopBeaconScan();
        }
    }
}
