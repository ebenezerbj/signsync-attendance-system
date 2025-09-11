import { PDFDocument, rgb } from 'pdf-lib';
import { AttendanceData } from '../types';

export class PdfExporter {
    async exportToPdf(data: AttendanceData[]): Promise<Buffer> {
        const pdfDoc = await PDFDocument.create();
        const page = pdfDoc.addPage([600, 400]);
        const { width, height } = page.getSize();

        page.drawText('Attendance Report', {
            x: 50,
            y: height - 50,
            size: 30,
            color: rgb(0, 0, 0),
        });

        let yPosition = height - 100;
        data.forEach(record => {
            page.drawText(`ID: ${record.id}, Name: ${record.name}, Date: ${record.date}, Status: ${record.status}`, {
                x: 50,
                y: yPosition,
                size: 12,
                color: rgb(0, 0, 0),
            });
            yPosition -= 20;
        });

        const pdfBytes = await pdfDoc.save();
        return Buffer.from(pdfBytes);
    }
}