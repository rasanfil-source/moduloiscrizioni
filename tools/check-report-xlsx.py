import sys, zipfile, openpyxl, io
path = sys.argv[1]
with zipfile.ZipFile(path) as archive:
    assert archive.testzip() is None
with open(path, 'rb') as stream:
    book = openpyxl.load_workbook(io.BytesIO(stream.read()))
sheet = book.active
assert sheet.max_row == 266, sheet.max_row
assert sheet.freeze_panes == 'A2'
assert sheet.auto_filter.ref
headers = [cell.value for cell in sheet[1]]
assert 'D1' in headers and 'D2' in headers, headers
assert 'Telefono accompagnatore' not in headers
assert sheet.cell(2, headers.index('D1') + 1).value == '=1+1'
assert sheet.cell(2, headers.index('D1') + 1).data_type == 's'
assert sheet.cell(3, headers.index('D1') + 1).value == '001234'
assert sheet.cell(4, headers.index('D1') + 1).value == 'Città & <test>\nSeconda riga'
print('PASS XLSX: 265 persone, D1/D2, filtri, prima riga bloccata, Unicode, zeri e testo non eseguibile.')
