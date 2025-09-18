package com.signsync.attendance.utils;

import android.Manifest;
import android.app.Activity;
import android.content.Context;
import android.content.pm.PackageManager;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import com.karumi.dexter.Dexter;
import com.karumi.dexter.MultiplePermissionsReport;
import com.karumi.dexter.PermissionToken;
import com.karumi.dexter.listener.PermissionRequest;
import com.karumi.dexter.listener.multi.MultiplePermissionsListener;
import java.util.List;

public class PermissionHelper {
    
    // Permission request codes
    public static final int REQUEST_LOCATION_PERMISSION = 1001;
    public static final int REQUEST_CAMERA_PERMISSION = 1002;
    public static final int REQUEST_STORAGE_PERMISSION = 1003;
    public static final int REQUEST_PHONE_PERMISSION = 1004;
    public static final int REQUEST_SMS_PERMISSION = 1005;
    public static final int REQUEST_ALL_PERMISSIONS = 1006;
    
    // Required permissions for attendance tracking
    public static final String[] LOCATION_PERMISSIONS = {
            Manifest.permission.ACCESS_FINE_LOCATION,
            Manifest.permission.ACCESS_COARSE_LOCATION
    };
    
    public static final String[] CAMERA_PERMISSIONS = {
            Manifest.permission.CAMERA
    };
    
    public static final String[] STORAGE_PERMISSIONS = {
            Manifest.permission.READ_EXTERNAL_STORAGE,
            Manifest.permission.WRITE_EXTERNAL_STORAGE
    };
    
    public static final String[] PHONE_PERMISSIONS = {
            Manifest.permission.READ_PHONE_STATE,
            Manifest.permission.CALL_PHONE
    };
    
    public static final String[] SMS_PERMISSIONS = {
            Manifest.permission.SEND_SMS,
            Manifest.permission.RECEIVE_SMS,
            Manifest.permission.READ_SMS
    };
    
    public static final String[] ALL_PERMISSIONS = {
            Manifest.permission.ACCESS_FINE_LOCATION,
            Manifest.permission.ACCESS_COARSE_LOCATION,
            Manifest.permission.CAMERA,
            Manifest.permission.READ_EXTERNAL_STORAGE,
            Manifest.permission.WRITE_EXTERNAL_STORAGE,
            Manifest.permission.READ_PHONE_STATE,
            Manifest.permission.SEND_SMS,
            Manifest.permission.VIBRATE,
            Manifest.permission.WAKE_LOCK,
            Manifest.permission.INTERNET,
            Manifest.permission.ACCESS_NETWORK_STATE,
            Manifest.permission.ACCESS_WIFI_STATE
    };
    
    // Permission listener interface
    public interface PermissionListener {
        void onPermissionGranted();
        void onPermissionDenied(List<String> deniedPermissions);
        void onPermissionPermanentlyDenied(List<String> permanentlyDeniedPermissions);
    }
    
    // Check if location permissions are granted
    public static boolean hasLocationPermissions(Context context) {
        return hasPermissions(context, LOCATION_PERMISSIONS);
    }
    
    // Check if camera permission is granted
    public static boolean hasCameraPermission(Context context) {
        return hasPermissions(context, CAMERA_PERMISSIONS);
    }
    
    // Check if storage permissions are granted
    public static boolean hasStoragePermissions(Context context) {
        return hasPermissions(context, STORAGE_PERMISSIONS);
    }
    
    // Check if phone permissions are granted
    public static boolean hasPhonePermissions(Context context) {
        return hasPermissions(context, PHONE_PERMISSIONS);
    }
    
    // Check if SMS permissions are granted
    public static boolean hasSMSPermissions(Context context) {
        return hasPermissions(context, SMS_PERMISSIONS);
    }
    
    // Check if all required permissions are granted
    public static boolean hasAllRequiredPermissions(Context context) {
        return hasPermissions(context, ALL_PERMISSIONS);
    }
    
    // Generic method to check multiple permissions
    public static boolean hasPermissions(Context context, String... permissions) {
        if (context != null && permissions != null) {
            for (String permission : permissions) {
                if (ContextCompat.checkSelfPermission(context, permission) != PackageManager.PERMISSION_GRANTED) {
                    return false;
                }
            }
        }
        return true;
    }
    
    // Request location permissions
    public static void requestLocationPermissions(Activity activity, PermissionListener listener) {
        requestPermissions(activity, LOCATION_PERMISSIONS, listener);
    }
    
    // Request camera permission
    public static void requestCameraPermission(Activity activity, PermissionListener listener) {
        requestPermissions(activity, CAMERA_PERMISSIONS, listener);
    }
    
    // Request storage permissions
    public static void requestStoragePermissions(Activity activity, PermissionListener listener) {
        requestPermissions(activity, STORAGE_PERMISSIONS, listener);
    }
    
    // Request phone permissions
    public static void requestPhonePermissions(Activity activity, PermissionListener listener) {
        requestPermissions(activity, PHONE_PERMISSIONS, listener);
    }
    
    // Request SMS permissions
    public static void requestSMSPermissions(Activity activity, PermissionListener listener) {
        requestPermissions(activity, SMS_PERMISSIONS, listener);
    }
    
    // Request all required permissions
    public static void requestAllPermissions(Activity activity, PermissionListener listener) {
        requestPermissions(activity, ALL_PERMISSIONS, listener);
    }
    
    // Generic method to request multiple permissions using Dexter
    public static void requestPermissions(Activity activity, String[] permissions, PermissionListener listener) {
        Dexter.withActivity(activity)
                .withPermissions(permissions)
                .withListener(new MultiplePermissionsListener() {
                    @Override
                    public void onPermissionsChecked(MultiplePermissionsReport report) {
                        if (report.areAllPermissionsGranted()) {
                            if (listener != null) {
                                listener.onPermissionGranted();
                            }
                        } else {
                            List<String> deniedPermissions = getDeniedPermissionNames(report.getDeniedPermissionResponses());
                            List<String> permanentlyDeniedPermissions = getPermanentlyDeniedPermissionNames(report.getDeniedPermissionResponses());
                            
                            if (!permanentlyDeniedPermissions.isEmpty()) {
                                if (listener != null) {
                                    listener.onPermissionPermanentlyDenied(permanentlyDeniedPermissions);
                                }
                            } else {
                                if (listener != null) {
                                    listener.onPermissionDenied(deniedPermissions);
                                }
                            }
                        }
                    }
                    
                    @Override
                    public void onPermissionRationaleShouldBeShown(List<PermissionRequest> permissions, PermissionToken token) {
                        token.continuePermissionRequest();
                    }
                })
                .check();
    }
    
    // Get denied permission names from responses
    private static List<String> getDeniedPermissionNames(List<com.karumi.dexter.listener.PermissionDeniedResponse> responses) {
        java.util.ArrayList<String> deniedPermissions = new java.util.ArrayList<>();
        for (com.karumi.dexter.listener.PermissionDeniedResponse response : responses) {
            deniedPermissions.add(response.getPermissionName());
        }
        return deniedPermissions;
    }
    
    // Get permanently denied permission names from responses
    private static List<String> getPermanentlyDeniedPermissionNames(List<com.karumi.dexter.listener.PermissionDeniedResponse> responses) {
        java.util.ArrayList<String> permanentlyDeniedPermissions = new java.util.ArrayList<>();
        for (com.karumi.dexter.listener.PermissionDeniedResponse response : responses) {
            if (response.isPermanentlyDenied()) {
                permanentlyDeniedPermissions.add(response.getPermissionName());
            }
        }
        return permanentlyDeniedPermissions;
    }
    
    // Show permission rationale dialog
    public static void showPermissionRationaleDialog(Activity activity, String title, String message, 
                                                   String positiveButton, String negativeButton,
                                                   Runnable onPositive, Runnable onNegative) {
        new androidx.appcompat.app.AlertDialog.Builder(activity)
                .setTitle(title)
                .setMessage(message)
                .setPositiveButton(positiveButton, (dialog, which) -> {
                    if (onPositive != null) {
                        onPositive.run();
                    }
                })
                .setNegativeButton(negativeButton, (dialog, which) -> {
                    if (onNegative != null) {
                        onNegative.run();
                    }
                })
                .setCancelable(false)
                .show();
    }
    
    // Show settings dialog for permanently denied permissions
    public static void showSettingsDialog(Activity activity) {
        new androidx.appcompat.app.AlertDialog.Builder(activity)
                .setTitle("Permission Required")
                .setMessage("Some permissions are required for the app to work properly. Please grant them in app settings.")
                .setPositiveButton("Go to Settings", (dialog, which) -> {
                    android.content.Intent intent = new android.content.Intent(android.provider.Settings.ACTION_APPLICATION_DETAILS_SETTINGS);
                    android.net.Uri uri = android.net.Uri.fromParts("package", activity.getPackageName(), null);
                    intent.setData(uri);
                    activity.startActivity(intent);
                })
                .setNegativeButton("Cancel", null)
                .show();
    }
    
    // Get permission display name
    public static String getPermissionDisplayName(String permission) {
        switch (permission) {
            case Manifest.permission.ACCESS_FINE_LOCATION:
            case Manifest.permission.ACCESS_COARSE_LOCATION:
                return "Location";
            case Manifest.permission.CAMERA:
                return "Camera";
            case Manifest.permission.READ_EXTERNAL_STORAGE:
            case Manifest.permission.WRITE_EXTERNAL_STORAGE:
                return "Storage";
            case Manifest.permission.READ_PHONE_STATE:
            case Manifest.permission.CALL_PHONE:
                return "Phone";
            case Manifest.permission.SEND_SMS:
            case Manifest.permission.RECEIVE_SMS:
            case Manifest.permission.READ_SMS:
                return "SMS";
            default:
                return "Unknown";
        }
    }
    
    // Get permission explanation
    public static String getPermissionExplanation(String permission) {
        switch (permission) {
            case Manifest.permission.ACCESS_FINE_LOCATION:
            case Manifest.permission.ACCESS_COARSE_LOCATION:
                return "Required for attendance location tracking and geofencing";
            case Manifest.permission.CAMERA:
                return "Required for taking profile pictures and scanning QR codes";
            case Manifest.permission.READ_EXTERNAL_STORAGE:
            case Manifest.permission.WRITE_EXTERNAL_STORAGE:
                return "Required for saving and accessing attendance reports and documents";
            case Manifest.permission.READ_PHONE_STATE:
                return "Required for device identification and security";
            case Manifest.permission.SEND_SMS:
                return "Required for sending attendance notifications via SMS";
            case Manifest.permission.CALL_PHONE:
                return "Required for emergency contact features";
            default:
                return "Required for app functionality";
        }
    }
    
    // Check if permission is critical (app cannot function without it)
    public static boolean isCriticalPermission(String permission) {
        switch (permission) {
            case Manifest.permission.ACCESS_FINE_LOCATION:
            case Manifest.permission.ACCESS_COARSE_LOCATION:
                return true; // Location is critical for attendance tracking
            case Manifest.permission.CAMERA:
                return false; // Camera is optional
            case Manifest.permission.READ_EXTERNAL_STORAGE:
            case Manifest.permission.WRITE_EXTERNAL_STORAGE:
                return false; // Storage is optional
            default:
                return false;
        }
    }
}
