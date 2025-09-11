import express from 'express';
import { ExcelExporter } from './exports/excelExporter';
import { PdfExporter } from './exports/pdfExporter';
import { attendanceData } from './data/attendanceData';

const app = express();
const port = 3000;

app.get('/export/excel', (req, res) => {
    const exporter = new ExcelExporter();
    const excelBuffer = exporter.exportToExcel(attendanceData);
    res.setHeader('Content-Disposition', 'attachment; filename=attendance.xlsx');
    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    res.send(excelBuffer);
});

app.get('/export/pdf', (req, res) => {
    const exporter = new PdfExporter();
    const pdfBuffer = exporter.exportToPdf(attendanceData);
    res.setHeader('Content-Disposition', 'attachment; filename=attendance.pdf');
    res.setHeader('Content-Type', 'application/pdf');
    res.send(pdfBuffer);
});

app.listen(port, () => {
    console.log(`Server is running on http://localhost:${port}`);
});