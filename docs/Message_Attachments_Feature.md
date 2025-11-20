# Message Image Upload Feature

## Overview
The message system now supports file attachments including images, documents, and other file types. This implementation uses a separate `MessageAttachment` entity for better scalability and maintainability.

## Features
- **Multiple file types**: Images (JPEG, PNG, GIF, WebP), PDF, Word documents, text files
- **File size limit**: 10MB per file
- **Secure access**: Only users with access to the order can download attachments
- **Organized storage**: Files are stored in year/month directory structure
- **Metadata tracking**: Original filename, file size, MIME type, upload timestamp

## API Usage

### Sending Messages with Attachments

#### Traditional JSON (still supported)
```json
POST /api/messages
Content-Type: application/json

{
    "order_id": "encrypted_order_id",
    "sender_id": "encrypted_sender_id",
    "receiver_id": "encrypted_receiver_id",
    "content": "Your message text"
}
```

#### Form Data with Files
```javascript
POST /api/messages
Content-Type: multipart/form-data

FormData:
- order_id: encrypted_order_id
- sender_id: encrypted_sender_id  
- receiver_id: encrypted_receiver_id
- content: Your message text
- files[]: file1.jpg
- files[]: document.pdf
```

### Response Format
```json
{
    "data": {
        "encryptedId": "encrypted_message_id",
        "order_id": "encrypted_order_id",
        "sender_id": "encrypted_sender_id",
        "receiver_id": "encrypted_receiver_id",
        "content": "Your message text",
        "sent_at": "2025-10-22 17:01:56",
        "attachments": [
            {
                "id": "encrypted_attachment_id",
                "filename": "safe-filename-unique.jpg",
                "originalFilename": "my-image.jpg",
                "mimeType": "image/jpeg",
                "attachmentType": "image",
                "fileSize": 1024000,
                "formattedFileSize": "1 MB",
                "uploadedAt": "2025-10-22 17:01:56",
                "downloadUrl": "/api/messages/attachments/encrypted_attachment_id"
            }
        ]
    },
    "status": "success",
    "message": "Message envoyé avec succès"
}
```

### Downloading Attachments
```
GET /api/messages/attachments/{encrypted_attachment_id}
Authorization: Bearer token
```

## File Storage Structure
```
var/uploads/messages/
├── 2025/
│   ├── 10/
│   │   ├── image-abc123.jpg
│   │   └── document-def456.pdf
│   └── 11/
└── 2026/
```

## Real-time Updates (Mercure)
The system automatically publishes attachment information via Mercure for real-time updates:

```json
{
    "id": "encrypted_message_id",
    "order_id": "encrypted_order_id", 
    "sender_id": "encrypted_sender_id",
    "sender_name": "User Name",
    "receiver_id": "encrypted_receiver_id", 
    "receiver_name": "Receiver Name",
    "content": "Message content",
    "sent_at": "2025-10-22T17:01:56+00:00",
    "attachments": [
        {
            "id": "encrypted_attachment_id",
            "filename": "safe-filename.jpg",
            "originalFilename": "user-image.jpg",
            "mimeType": "image/jpeg",
            "attachmentType": "image", 
            "fileSize": 1024000,
            "formattedFileSize": "1 MB",
            "uploadedAt": "2025-10-22T17:01:56+00:00",
            "downloadUrl": "/api/messages/attachments/encrypted_attachment_id"
        }
    ]
}
```

This allows frontend applications to immediately display new attachments without polling.

## Security Features
- **Access Control**: Users can only download attachments for messages they have access to
- **File Validation**: MIME type and file size validation
- **Secure Filenames**: Original filenames are slugified and made unique
- **Error Logging**: All file operations are logged for security auditing

## Database Schema
### `message_attachments` table
- `id` - Primary key
- `message_id` - Foreign key to messages table (CASCADE DELETE)
- `filename` - Safe filename on filesystem
- `original_filename` - Original filename from upload
- `file_path` - Relative path from uploads directory
- `mime_type` - MIME type of the file
- `file_size` - File size in bytes
- `uploaded_at` - Upload timestamp
- `attachment_type` - Category: image, document, video, audio, other

## Configuration
- Maximum file size: 10MB (configurable in `FileUploadService`)
- Allowed file types: Images, PDF, Word docs, text files
- Upload directory: `var/uploads/messages/`
- Files organized by year/month subdirectories

## Error Handling
The system handles various error scenarios:
- Invalid file types
- File size exceeded
- Upload failures
- File not found
- Access denied
- Corrupted files

All errors are logged and return appropriate HTTP status codes with descriptive error messages.