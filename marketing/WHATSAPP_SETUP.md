# WhatsApp Marketing Feature - Setup Instructions

## Overview
The WhatsApp Marketing feature allows users to send messages to customers and CRM leads with automatic file uploads, shareable links, and custom message templates.

## Files Created/Modified

### 1. API Endpoints
**File:** `/api/upload_whatsapp_file.php`
- Handles file uploads with validation
- File size limit: 10MB
- Supported file types: Images (JPG, PNG, GIF, WebP), PDF, Word, Excel, Video (MP4, MOV), Audio (MP3, WAV)
- Returns JSON response with upload status and shareable URL

**File:** `/api/whatsapp_templates.php`
- Manages custom message templates
- Actions: list, save, delete
- Templates stored in `whatsapp_templates` table

**File:** `/api/get_catalogs.php`
- Fetches all active catalogs from database with brochures
- Returns catalog name, code, model number, and brochure_path
- Filters to only show catalogs with brochures attached
- Used for catalog attachment selection

### 2. WhatsApp Page
**File:** `/marketing/whatsapp.php` (Updated)
- Enhanced with automatic file upload functionality
- Custom template saving and management
- Catalog attachment selection with modal preview
- File links are automatically included in WhatsApp messages (clickable format)
- Features:
  - Customer/CRM Lead selection with tabs
  - Default message templates (Greeting, Follow-up, Offer, Meeting Request)
  - Custom template creation and management
  - **NEW:** Catalog selector modal with grid preview
  - Automatic file upload on file selection
  - Shareable file link generation
  - Copy-to-clipboard functionality for file links
  - Message history logging via `whatsapp_log` table

### 3. Database Tables

**Run these SQL files to set up the database:**

**Table 1:** `whatsapp_log` (File: `setup_whatsapp.sql`)
```sql
CREATE TABLE IF NOT EXISTS whatsapp_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_type ENUM('customer', 'lead') NOT NULL,
    recipient_id INT NOT NULL,
    recipient_name VARCHAR(255),
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    attachment_name VARCHAR(255),
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sent_by INT,
    INDEX idx_recipient (recipient_type, recipient_id),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Table 2:** `whatsapp_templates` (File: `setup_templates.sql`)
```sql
CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    template_content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    UNIQUE KEY unique_template_name (template_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4. Sidebar
**File:** `/includes/sidebar.php` (Updated)
- Added "WhatsApp" link under Marketing section

## How It Works

### File Upload Flow
1. User selects a file from "Attachment" section
2. File is automatically uploaded to `/uploads/whatsapp/` directory
3. Upload status message shows success or error
4. Shareable download link is generated and displayed
5. User can copy the link to clipboard

### Message Sending Flow
1. User selects recipient (Customer or CRM Lead)
2. Types or selects a message template (default or custom)
3. Optionally selects a file attachment (auto-uploads)
4. Clicks "Send via WhatsApp"
5. File link (if any) is automatically appended to message in clickable format
6. Message is logged to database
7. WhatsApp Web/Desktop opens with pre-filled message and clickable link

### Custom Template Management
1. **Save Template**: Click "💾 Save Current as Template" button
   - Enter a unique template name
   - Current message content is saved
2. **Use Template**: Click on custom template button to load it into message field
3. **Manage Templates**: Click "⚙️ Manage Templates" button
   - View all saved templates
   - Use any template directly
   - Delete unwanted templates

## File Uploads Directory
- **Location:** `/uploads/whatsapp/`
- **Auto-created** on first file upload
- **Unique naming:** `wa_[timestamp].[extension]`
- **Access:** Via `/uploads/whatsapp/filename` URL
- **Git:** Excluded via `.gitignore`

## Supported File Types
- **Images:** JPG, PNG, GIF, WebP
- **Documents:** PDF, Word (.doc, .docx), Excel (.xls, .xlsx)
- **Media:** MP4, MOV, MP3, WAV

## Validation
- Maximum file size: 10MB
- File type validation on server-side
- Secure file naming to prevent conflicts
- Unique template names enforced

## Features
✅ Automatic file upload on selection
✅ Shareable download links (clickable in WhatsApp)
✅ Copy-to-clipboard functionality
✅ File link included in WhatsApp message
✅ Upload status feedback
✅ Message history logging
✅ Customer/Lead selection with phone formatting
✅ Default message templates
✅ Custom template creation and saving
✅ Template management modal
✅ Edit/delete custom templates
✅ **NEW:** Catalog selection modal with preview grid
✅ **NEW:** Catalog attachment with automatic file path linking
✅ **NEW:** Browse and select from existing catalogs
✅ WhatsApp Web/Desktop integration

## Database Setup Instructions
1. Open phpMyAdmin or MySQL client
2. Run `marketing/setup_whatsapp.sql` to create `whatsapp_log` table
3. Run `marketing/setup_templates.sql` to create `whatsapp_templates` table

Or via command line:
```bash
mysql -u root -p your_database < c:/xampp/htdocs/marketing/setup_whatsapp.sql
mysql -u root -p your_database < c:/xampp/htdocs/marketing/setup_templates.sql
```

## Testing Checklist
- [ ] Upload a file - should auto-upload and show shareable link
- [ ] Copy file link to clipboard
- [ ] Send message with attached file - link should appear in message and be clickable
- [ ] Send message without file - should work normally
- [ ] Check `whatsapp_log` table for sent messages
- [ ] Test with different file types
- [ ] Test with file over 10MB - should show error
- [ ] Save a custom template
- [ ] Load a custom template
- [ ] Manage templates (view/delete)
- [ ] Template name uniqueness validation

## Notes
- File links are formatted without emoji to ensure clickability in WhatsApp
- Templates support {name} placeholder for recipient name personalization
- Custom templates are stored per-installation (not per-user)
- Template names must be unique
