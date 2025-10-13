# Gemini Project Plan

## October 10, 2025

### Initial Implementation & Bug Fixes
- **Bug Fix: Page Scroll on Return:** Fixed an issue in `controle_turma.php` where the page would scroll to the top after returning a book. Replaced the page reload with a dynamic UI update.
- **Bug Fix: UI Not Updating on Cancellation:** Corrected the client-side script in `controle_turma.php` to ensure the UI updates instantly when a loan is canceled.

### Feature: Technical Reserve (First Pass - Flawed)
- An initial version of the Technical Reserve feature was implemented by adding a single `reserva_tecnica` column to the `livros` table.
- This implementation was flawed as it did not account for the different conservation statuses of the books in reserve.

---

## October 10, 2025 (Refactoring)

### Feature: Technical Reserve (Complete Refactoring)
- **Corrected Database Schema:** The flawed `reserva_tecnica` column was removed from the `livros` table. A new, dedicated `reserva_tecnica` table was created to store stock counts per book, per conservation status, and per school year. The `criar_db.php` script was updated to reflect this correct structure.
- **Rewritten Backend APIs:** The APIs (`get_reserva_tecnica.php`, `update_reserva_tecnica.php`, `marcar_como_perdido.php`) were completely rewritten to work with the new, correct database table structure.
- **Rebuilt Frontend UI:**
    - The `public/reserva_tecnica.php` page was rebuilt from scratch. It now features a modal-based interface to view and manage the reserve stock for each conservation status individually.
    - The "Mark as Lost" workflow in `public/controle_turma.php` was updated. It now opens a modal for the user to select the conservation status of the replacement book being taken from the reserve, making the process more accurate.

### UI/UX Improvements
- **Navbar Reorganization:** The main navigation bar was restructured for better organization. "Dashboard" was renamed to "Painel de Controle", and several management pages were grouped into a new "Configurações" dropdown menu.

---

## Future Considerations & Roadmap

### Concerns
1.  **Automatic Reserve Decrement on "Lost Book":** The current workflow automatically decrements the technical reserve when a book is marked as lost. This assumes a replacement is always given from the school's stock. This might be inefficient or incorrect if the school's policy is different (e.g., the student must pay for the lost book, and a replacement is not immediately provided from the reserve). A manual confirmation step or a different workflow might be more flexible.
2.  **Stock Integrity on "Lost Book":** The system currently attempts to decrement the reserve count but does not explicitly warn the user if the stock for the selected conservation status is already zero. This could lead to a silent failure where the loan is marked as "Perdido" but no book was actually available in the reserve, creating a data discrepancy.
3.  **Lack of General-Purpose Stock Adjustments:** The only way to decrement the reserve is when a student loses a book. There is no workflow for writing off books that are found damaged in the library, or for other administrative adjustments. While the management page allows setting the quantity, it's not ideal for auditing purposes. A dedicated "Stock Adjustment" feature with a field for "Reason" could be valuable.
4.  **Inventory Reports Accuracy:** The user has reported that the inventory reports (`relatorios.php`) are not currently functioning correctly. The source of the inaccuracy needs to be investigated. This could be related to how statuses ('Emprestado', 'Devolvido', 'Perdido') are being aggregated or to other logical errors in the report generation APIs.

### Planned Features
- **Teacher Textbook Control:** Develop a new module to manage and track textbooks delivered to teachers. This would likely function similarly to the student loan system but would require a separate data model (e.g., `professores` and `emprestimos_professores` tables).
