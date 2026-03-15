package com.signsync.attendance;

import android.app.Activity;
import android.app.AlertDialog;
import android.os.Bundle;
import android.os.AsyncTask;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;
import java.io.BufferedReader;
import java.io.DataOutputStream;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import org.json.JSONObject;

public class MainActivity extends Activity {
    private EditText employeeIdInput;
    private EditText pinInput;
    private Button clockInBtn;
    private Button clockOutBtn;
    private TextView statusText;
    private boolean isClockedIn = false;
    private String authenticatedEmployeeId = "";
    private String authenticatedEmployeeName = "";
    private boolean needsPinSetup = false;
    
    // Backend API URLs - Production Fly.io deployment
    private static final String API_BASE = "https://signsync-attendance.fly.dev";
    private static final String PIN_API_URL = API_BASE + "/signsync_pin_api.php";
    private static final String CLOCKINOUT_API_URL = API_BASE + "/wearos_api.php";
    
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        
        // Create layout programmatically for Watch AOS 1.7.3 compatibility
        LinearLayout layout = new LinearLayout(this);
        layout.setOrientation(LinearLayout.VERTICAL);
        layout.setPadding(20, 20, 20, 20);
        
        // Title
        TextView titleText = new TextView(this);
        titleText.setText("SIGNSYNC");
        titleText.setTextSize(18);
        titleText.setPadding(0, 0, 0, 20);
        layout.addView(titleText);
        
        // Subtitle
        TextView subtitleText = new TextView(this);
        subtitleText.setText("Watch AOS 1.7.3");
        subtitleText.setTextSize(10);
        subtitleText.setPadding(0, 0, 0, 10);
        layout.addView(subtitleText);
        
        // Status
        statusText = new TextView(this);
        statusText.setText("Status: READY");
        statusText.setTextSize(14);
        statusText.setPadding(0, 0, 0, 10);
        layout.addView(statusText);
        
        // Employee ID input
        TextView empIdLabel = new TextView(this);
        empIdLabel.setText("Employee ID:");
        empIdLabel.setTextSize(12);
        layout.addView(empIdLabel);
        
        employeeIdInput = new EditText(this);
        employeeIdInput.setHint("Enter ID");
        employeeIdInput.setTextSize(12);
        layout.addView(employeeIdInput);
        
        // PIN input
        TextView pinLabel = new TextView(this);
        pinLabel.setText("PIN:");
        pinLabel.setTextSize(12);
        layout.addView(pinLabel);
        
        pinInput = new EditText(this);
        pinInput.setHint("Enter PIN");
        pinInput.setInputType(android.text.InputType.TYPE_CLASS_NUMBER | android.text.InputType.TYPE_NUMBER_VARIATION_PASSWORD);
        pinInput.setTextSize(12);
        layout.addView(pinInput);
        
        // Clock In button
        clockInBtn = new Button(this);
        clockInBtn.setText("CLOCK IN");
        clockInBtn.setTextSize(12);
        clockInBtn.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                handleClockIn();
            }
        });
        layout.addView(clockInBtn);
        
        // Clock Out button
        clockOutBtn = new Button(this);
        clockOutBtn.setText("CLOCK OUT");
        clockOutBtn.setTextSize(12);
        clockOutBtn.setEnabled(false);
        clockOutBtn.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                handleClockOut();
            }
        });
        layout.addView(clockOutBtn);
        
        setContentView(layout);
    }
    
    private void handleClockIn() {
        String empId = employeeIdInput.getText().toString().trim();
        String pin = pinInput.getText().toString().trim();
        
        if (empId.isEmpty() || pin.isEmpty()) {
            Toast.makeText(this, "Please enter Employee ID and PIN", Toast.LENGTH_SHORT).show();
            return;
        }
        
        statusText.setText("Status: AUTHENTICATING...");
        new AuthenticateTask(empId, pin).execute();
    }
    
    private void handleClockOut() {
        if (authenticatedEmployeeId.isEmpty()) {
            Toast.makeText(this, "Not authenticated", Toast.LENGTH_SHORT).show();
            return;
        }
        
        statusText.setText("Status: CLOCKING OUT...");
        new ClockInOutTask(authenticatedEmployeeId, "clock_out").execute();
    }
    
    private void updateUIState() {
        if (isClockedIn) {
            statusText.setText("Status: CLOCKED IN - " + authenticatedEmployeeName);
            clockInBtn.setEnabled(false);
            clockOutBtn.setEnabled(true);
            employeeIdInput.setEnabled(false);
            pinInput.setEnabled(false);
        } else {
            statusText.setText("Status: READY");
            clockInBtn.setEnabled(true);
            clockOutBtn.setEnabled(false);
            employeeIdInput.setEnabled(true);
            pinInput.setEnabled(true);
            authenticatedEmployeeId = "";
            authenticatedEmployeeName = "";
            employeeIdInput.setText("");
            pinInput.setText("");
        }
    }
    
    // AsyncTask for PIN authentication
    private class AuthenticateTask extends AsyncTask<Void, Void, String> {
        private String employeeId;
        private String pin;
        
        public AuthenticateTask(String employeeId, String pin) {
            this.employeeId = employeeId;
            this.pin = pin;
        }
        
        @Override
        protected String doInBackground(Void... voids) {
            try {
                URL url = new URL(PIN_API_URL);
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setDoOutput(true);
                
                JSONObject json = new JSONObject();
                json.put("employee_id", employeeId);
                json.put("pin", pin);
                
                DataOutputStream wr = new DataOutputStream(conn.getOutputStream());
                wr.writeBytes(json.toString());
                wr.flush();
                wr.close();
                
                BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                String inputLine;
                StringBuilder response = new StringBuilder();
                while ((inputLine = in.readLine()) != null) {
                    response.append(inputLine);
                }
                in.close();
                
                return response.toString();
            } catch (Exception e) {
                return "ERROR: " + e.getMessage();
            }
        }
        
        @Override
        protected void onPostExecute(String result) {
            try {
                if (result.startsWith("ERROR:")) {
                    statusText.setText("Status: NETWORK ERROR");
                    Toast.makeText(MainActivity.this, result, Toast.LENGTH_LONG).show();
                    return;
                }
                
                JSONObject response = new JSONObject(result);
                if (response.getBoolean("success")) {
                    JSONObject data = response.getJSONObject("data");
                    authenticatedEmployeeId = data.getString("employee_id");
                    authenticatedEmployeeName = data.getString("name");
                    
                    // Check if user needs PIN setup
                    boolean needsSetup = data.optBoolean("needs_pin_setup", false);
                    boolean hasCustomPin = data.optBoolean("has_custom_pin", false);
                    
                    if (needsSetup && !hasCustomPin) {
                        // Show PIN setup dialog
                        showPinSetupDialog();
                    } else {
                        // Proceed with clock in
                        statusText.setText("Status: CLOCKING IN...");
                        new ClockInOutTask(authenticatedEmployeeId, "clock_in").execute();
                    }
                } else {
                    statusText.setText("Status: AUTH FAILED");
                    Toast.makeText(MainActivity.this, response.getString("message"), Toast.LENGTH_SHORT).show();
                }
            } catch (Exception e) {
                statusText.setText("Status: PARSE ERROR");
                Toast.makeText(MainActivity.this, "Response parse error: " + e.getMessage(), Toast.LENGTH_SHORT).show();
            }
        }
    }
    
    // AsyncTask for clock in/out
    private class ClockInOutTask extends AsyncTask<Void, Void, String> {
        private String employeeId;
        private String action;
        
        public ClockInOutTask(String employeeId, String action) {
            this.employeeId = employeeId;
            this.action = action;
        }
        
        @Override
        protected String doInBackground(Void... voids) {
            try {
                URL url = new URL(CLOCKINOUT_API_URL);
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setDoOutput(true);
                
                JSONObject json = new JSONObject();
                json.put("employee_id", employeeId);
                json.put("action", action);
                
                DataOutputStream wr = new DataOutputStream(conn.getOutputStream());
                wr.writeBytes(json.toString());
                wr.flush();
                wr.close();
                
                BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                String inputLine;
                StringBuilder response = new StringBuilder();
                while ((inputLine = in.readLine()) != null) {
                    response.append(inputLine);
                }
                in.close();
                
                return response.toString();
            } catch (Exception e) {
                return "ERROR: " + e.getMessage();
            }
        }
        
        @Override
        protected void onPostExecute(String result) {
            try {
                if (result.startsWith("ERROR:")) {
                    statusText.setText("Status: NETWORK ERROR");
                    Toast.makeText(MainActivity.this, result, Toast.LENGTH_LONG).show();
                    return;
                }
                
                JSONObject response = new JSONObject(result);
                if (response.getBoolean("success")) {
                    if (action.equals("clock_in")) {
                        isClockedIn = true;
                        Toast.makeText(MainActivity.this, "Clocked In Successfully!", Toast.LENGTH_SHORT).show();
                    } else {
                        isClockedIn = false;
                        Toast.makeText(MainActivity.this, "Clocked Out Successfully!", Toast.LENGTH_SHORT).show();
                    }
                    updateUIState();
                } else {
                    statusText.setText("Status: " + action.toUpperCase() + " FAILED");
                    Toast.makeText(MainActivity.this, response.getString("message"), Toast.LENGTH_SHORT).show();
                }
            } catch (Exception e) {
                statusText.setText("Status: PARSE ERROR");
                Toast.makeText(MainActivity.this, "Response parse error: " + e.getMessage(), Toast.LENGTH_SHORT).show();
            }
        }
    }
    
    // Show PIN setup dialog for first-time users
    private void showPinSetupDialog() {
        AlertDialog.Builder builder = new AlertDialog.Builder(this);
        builder.setTitle("Create Your Personal PIN");
        builder.setMessage("Welcome! You're using the default PIN (1234).\nCreate your personal PIN for future logins:");
        
        final EditText pinSetupInput = new EditText(this);
        pinSetupInput.setHint("Enter 4-8 digit PIN");
        pinSetupInput.setInputType(android.text.InputType.TYPE_CLASS_NUMBER | android.text.InputType.TYPE_NUMBER_VARIATION_PASSWORD);
        builder.setView(pinSetupInput);
        
        builder.setPositiveButton("Create PIN", (dialog, which) -> {
            String newPin = pinSetupInput.getText().toString().trim();
            if (validateNewPin(newPin)) {
                statusText.setText("Status: SETTING UP PIN...");
                new PinSetupTask(authenticatedEmployeeId, newPin).execute();
            }
        });
        
        builder.setNegativeButton("Skip for Now", (dialog, which) -> {
            // Proceed with clock in without PIN setup
            statusText.setText("Status: CLOCKING IN...");
            new ClockInOutTask(authenticatedEmployeeId, "clock_in").execute();
        });
        
        builder.setCancelable(false);
        builder.show();
    }
    
    // Validate new PIN format
    private boolean validateNewPin(String pin) {
        if (pin.length() < 4 || pin.length() > 8) {
            Toast.makeText(this, "PIN must be 4-8 digits", Toast.LENGTH_SHORT).show();
            return false;
        }
        
        if (!pin.matches("\\d+")) {
            Toast.makeText(this, "PIN must contain only numbers", Toast.LENGTH_SHORT).show();
            return false;
        }
        
        return true;
    }
    
    // AsyncTask for PIN setup
    private class PinSetupTask extends AsyncTask<Void, Void, String> {
        private String employeeId;
        private String newPin;
        
        public PinSetupTask(String employeeId, String newPin) {
            this.employeeId = employeeId;
            this.newPin = newPin;
        }
        
        @Override
        protected String doInBackground(Void... voids) {
            try {
                URL url = new URL(PIN_API_URL);
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json");
                conn.setDoOutput(true);
                
                JSONObject json = new JSONObject();
                json.put("action", "setup_pin");
                json.put("employee_id", employeeId);
                json.put("new_pin", newPin);
                
                DataOutputStream wr = new DataOutputStream(conn.getOutputStream());
                wr.writeBytes(json.toString());
                wr.flush();
                wr.close();
                
                BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                String inputLine;
                StringBuilder response = new StringBuilder();
                while ((inputLine = in.readLine()) != null) {
                    response.append(inputLine);
                }
                in.close();
                
                return response.toString();
            } catch (Exception e) {
                return "ERROR: " + e.getMessage();
            }
        }
        
        @Override
        protected void onPostExecute(String result) {
            try {
                if (result.startsWith("ERROR:")) {
                    statusText.setText("Status: PIN SETUP FAILED");
                    Toast.makeText(MainActivity.this, result, Toast.LENGTH_LONG).show();
                    return;
                }
                
                JSONObject response = new JSONObject(result);
                if (response.getBoolean("success")) {
                    Toast.makeText(MainActivity.this, "Personal PIN created successfully!", Toast.LENGTH_LONG).show();
                    statusText.setText("Status: CLOCKING IN...");
                    new ClockInOutTask(authenticatedEmployeeId, "clock_in").execute();
                } else {
                    statusText.setText("Status: PIN SETUP FAILED");
                    Toast.makeText(MainActivity.this, response.getString("message"), Toast.LENGTH_SHORT).show();
                }
            } catch (Exception e) {
                statusText.setText("Status: PARSE ERROR");
                Toast.makeText(MainActivity.this, "PIN setup error: " + e.getMessage(), Toast.LENGTH_SHORT).show();
            }
        }
    }
}
