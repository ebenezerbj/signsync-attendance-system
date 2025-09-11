import * as ExcelJS from 'exceljs';
import { AttendanceData } from '../types';

export class ExcelExporter {
    exportToExcel(data: AttendanceData[]): Buffer {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet('Attendance Data');

        worksheet.columns = [
            { header: 'ID', key: 'id', width: 10 },
            { header: 'Name', key: 'name', width: 30 },
            { header: 'Date', key: 'date', width: 15 },
            { header: 'Status', key: 'status', width: 15 },
        ];

        data.forEach(record => {
            worksheet.addRow(record);
        });

        return workbook.xlsx.writeBuffer();
    }
}