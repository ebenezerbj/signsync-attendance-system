package com.signsync.attendance.activity.employee;

import android.os.Bundle;
import androidx.annotation.Nullable;
import androidx.appcompat.app.AppCompatActivity;
import com.signsync.attendance.R;

public class AddEditEmployeeActivity extends AppCompatActivity {
    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_add_edit_employee);
        setTitle("Add/Edit Employee");
    }
}
