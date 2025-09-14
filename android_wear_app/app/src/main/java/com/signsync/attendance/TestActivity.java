package com.signsync.attendance;

import android.app.Activity;
import android.os.Bundle;
import android.widget.TextView;

public class TestActivity extends Activity {
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        
        TextView textView = new TextView(this);
        textView.setText("SignSync Test App - Installation Successful!");
        textView.setTextSize(18);
        textView.setPadding(20, 20, 20, 20);
        
        setContentView(textView);
    }
}
