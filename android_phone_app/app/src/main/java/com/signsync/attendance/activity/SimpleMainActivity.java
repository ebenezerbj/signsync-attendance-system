package com.signsync.attendance.activity;

import android.os.Bundle;
import android.widget.TextView;
import androidx.appcompat.app.AppCompatActivity;
import com.signsync.attendance.R;

public class SimpleMainActivity extends AppCompatActivity {
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);
        
        TextView textView = findViewById(android.R.id.text1);
        if (textView != null) {
            textView.setText("SignSync Attendance System - Phone App\n\nThis is a comprehensive attendance tracking system with employee portal, kiosk mode, and administrative features.");
        }
    }
}
