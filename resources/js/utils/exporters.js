import { jsPDF } from "jspdf";
import autoTable from "jspdf-autotable";
import * as XLSX from "xlsx";
import { saveAs } from "file-saver";

export function exportPoliciesPDF({ title, rows }) {
  const doc = new jsPDF();
  doc.setFontSize(14);
  doc.text(title || "Reporte", 14, 14);

  autoTable(doc, {
    startY: 20,
    head: [["Folio", "Fecha", "Tipo", "Cargo", "Abono", "Estatus"]],
    body: (rows || []).map((r) => [
      r.folio,
      r.movement_date,
      r.policy_type,
      String(r.total_debit),
      String(r.total_credit),
      r.status,
    ]),
  });

  doc.save(`reporte-polizas-${Date.now()}.pdf`);
}

export function exportPoliciesExcel({ sheetName, rows }) {
  const data = (rows || []).map((r) => ({
    Folio: r.folio,
    Fecha: r.movement_date,
    Tipo: r.policy_type,
    Cargo: r.total_debit,
    Abono: r.total_credit,
    Estatus: r.status,
  }));

  const ws = XLSX.utils.json_to_sheet(data);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, sheetName || "Polizas");

  const out = XLSX.write(wb, { bookType: "xlsx", type: "array" });
  const blob = new Blob([out], { type: "application/octet-stream" });
  saveAs(blob, `reporte-polizas-${Date.now()}.xlsx`);
}

export function exportTableExcel({
  sheetName = "Reporte",
  rows = [],
  fileName = "reporte",
  company = null,
  title = "Reporte",
}) {
  const safeRows = Array.isArray(rows) ? rows : [];
  const aoa = [];

  aoa.push([title]);
  aoa.push([]);

  aoa.push(["DETALLES FISCALES"]);
  aoa.push(["Nombre Comercial", company?.name || ""]);
  aoa.push(["RFC", company?.rfc || ""]);
  aoa.push([
    "Régimen Fiscal",
    [company?.regimen_codigo, company?.regimen_fiscal].filter(Boolean).join(" - "),
  ]);
  aoa.push(["Dirección Legal", company?.address || ""]);
  aoa.push([]);

  if (safeRows.length && Array.isArray(safeRows[0])) {
    safeRows.forEach((row) => aoa.push(row));
  } else if (safeRows.length) {
    const headers = Object.keys(safeRows[0]);
    aoa.push(headers);
    safeRows.forEach((item) => {
      aoa.push(headers.map((h) => item[h]));
    });
  }

  const ws = XLSX.utils.aoa_to_sheet(aoa);

  ws["!cols"] = [
    { wch: 18 },
    { wch: 28 },
    { wch: 16 },
    { wch: 16 },
    { wch: 18 },
    { wch: 18 },
    { wch: 18 },
    { wch: 18 },
  ];

  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, sheetName);

  const out = XLSX.write(wb, { bookType: "xlsx", type: "array" });
  const blob = new Blob([out], { type: "application/octet-stream" });
  saveAs(blob, `${fileName}-${Date.now()}.xlsx`);
}

export function exportTablePDF({
  title = "Reporte",
  head = [],
  body = [],
  fileName = "reporte",
  company = null,
}) {
  const doc = new jsPDF();

  doc.setFontSize(16);
  doc.text(title, 14, 14);

  let y = 24;

  doc.setFontSize(11);
  doc.text("DETALLES FISCALES", 14, y);
  y += 8;

  const fiscalRows = [
    ["Nombre Comercial", company?.name || ""],
    ["RFC", company?.rfc || ""],
    [
      "Régimen Fiscal",
      [company?.regimen_codigo, company?.regimen_fiscal].filter(Boolean).join(" - "),
    ],
    ["Dirección Legal", company?.address || ""],
  ];

  autoTable(doc, {
    startY: y,
    head: [["Campo", "Valor"]],
    body: fiscalRows,
    theme: "grid",
    styles: { fontSize: 9 },
    headStyles: { fillColor: [37, 99, 235] },
    margin: { left: 14, right: 14 },
  });

  autoTable(doc, {
    startY: doc.lastAutoTable.finalY + 10,
    head: Array.isArray(head) ? head : [],
    body: Array.isArray(body) ? body : [],
    styles: { fontSize: 8 },
    headStyles: { fillColor: [37, 99, 235] },
    margin: { left: 14, right: 14 },
  });

  doc.save(`${fileName}-${Date.now()}.pdf`);
}