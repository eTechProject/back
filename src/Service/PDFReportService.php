<?php

namespace App\Service;

use App\Entity\Tasks;
use Psr\Log\LoggerInterface;

class PDFReportService
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Generate PDF from AI report data
     */
    public function generatePDFFromReport(string $reportContent, Tasks $task): string
    {
        try {
            // Convert Markdown content to HTML
            $htmlContent = $this->convertMarkdownToHtml($reportContent);
            
            // Generate PDF using TCPDF
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('Guard Security System');
            $pdf->SetAuthor('Guard Security');
            $pdf->SetTitle('Rapport de Mission - Task ID: ' . $task->getId());
            $pdf->SetSubject('Rapport de mission de sécurité');
            
            // Set margins
            $pdf->SetMargins(15, 20, 15);
            $pdf->SetHeaderMargin(10);
            $pdf->SetFooterMargin(10);
            
            // Add a page
            $pdf->AddPage();
            
            // Set font
            $pdf->SetFont('helvetica', '', 10);
            
            // Add header
            $this->addPDFHeader($pdf, $task);
            
            // Add content
            $pdf->writeHTML($htmlContent, true, false, true, false, '');
            
            // Add footer with generation date
            $this->addPDFFooter($pdf);
            
            // Generate PDF string
            return $pdf->Output('', 'S');
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération du PDF', [
                'task_id' => $task->getId(),
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Erreur lors de la génération du PDF: ' . $e->getMessage());
        }
    }

    /**
     * Convert Markdown to HTML for PDF generation
     */
    private function convertMarkdownToHtml(string $markdownContent): string
    {
        // Simple Markdown to HTML conversion
        $html = $markdownContent;
        
        // Convert headers
        $html = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $html);
        
        // Convert bold text
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
        
        // Convert bullet points
        $html = preg_replace('/^\* (.*$)/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);
        
        // Convert line breaks
        $html = nl2br($html);
        
        // Add basic styling
        $styledHtml = '
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            h1 { color: #2c3e50; font-size: 18px; margin-bottom: 10px; }
            h2 { color: #34495e; font-size: 16px; margin-bottom: 8px; margin-top: 15px; }
            h3 { color: #7f8c8d; font-size: 14px; margin-bottom: 6px; margin-top: 12px; }
            ul { margin-left: 20px; }
            li { margin-bottom: 3px; }
            strong { color: #2c3e50; }
        </style>
        ' . $html;
        
        return $styledHtml;
    }

    /**
     * Add PDF header
     */
    private function addPDFHeader(\TCPDF $pdf, Tasks $task): void
    {
        // Company logo and header info
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'RAPPORT DE MISSION DE SÉCURITÉ', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Système Guard Security', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Task information
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Informations de la Mission', 0, 1, 'L');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(40, 6, 'ID de la tâche:', 0, 0, 'L');
        $pdf->Cell(0, 6, $task->getId(), 0, 1, 'L');
        
        $pdf->Cell(40, 6, 'Type:', 0, 0, 'L');
        $pdf->Cell(0, 6, $task->getType()->value, 0, 1, 'L');
        
        $pdf->Cell(40, 6, 'Statut:', 0, 0, 'L');
        $pdf->Cell(0, 6, $task->getStatus()->value, 0, 1, 'L');
        
        $pdf->Ln(5);
        
        // Line separator
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(10);
    }

    /**
     * Add PDF footer
     */
    private function addPDFFooter(\TCPDF $pdf): void
    {
        $pdf->Ln(10);
        
        // Line separator
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(5);
        
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 5, 'Rapport généré automatiquement le ' . date('d/m/Y à H:i:s'), 0, 1, 'C');
        $pdf->Cell(0, 5, 'Guard Security System - Confidentiel', 0, 1, 'C');
    }

    /**
     * Get filename for the PDF
     */
    public function generatePDFFilename(Tasks $task): string
    {
        $date = date('Y-m-d');
        $taskId = $task->getId();
        $clientName = preg_replace('/[^a-zA-Z0-9]/', '_', $task->getOrder()->getClient()->getName());
        
        return "rapport_mission_{$taskId}_{$clientName}_{$date}.pdf";
    }
}
