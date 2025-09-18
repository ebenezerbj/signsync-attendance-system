package com.signsync.attendance.network.response;

import com.google.gson.annotations.SerializedName;
import com.signsync.attendance.model.Branch;
import java.util.List;

public class BranchResponse extends BaseResponse {
    @SerializedName("branches")
    private List<Branch> branches;

    public List<Branch> getBranches() {
        return branches;
    }

    public void setBranches(List<Branch> branches) {
        this.branches = branches;
    }

    // Convenience method for callers expecting a single branch
    public Branch getBranch() {
        return (branches != null && !branches.isEmpty()) ? branches.get(0) : null;
    }
}
