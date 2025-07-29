# Creative Business Frontend

A modern Next.js e-commerce frontend for creative businesses with comprehensive digital downloads support, built with TypeScript, Tailwind CSS, and cutting-edge React patterns.

## Features

### Core E-commerce
- 🛍️ Product catalog with advanced filtering and search
- 🛒 Smart shopping cart with persistence
- 💳 Secure checkout process
- 📦 Order tracking and management
- 👤 User account management
- ⭐ Product reviews and ratings

### Digital Downloads (NEW!)
- 📁 **Digital Product Library** - Manage all your digital purchases in one place
- 🔐 **Secure Downloads** - Token-based download system with expiration and limits
- 🔑 **License Management** - Comprehensive license key management and validation
- 📊 **Download Analytics** - Track download usage and statistics
- 🚀 **Progress Tracking** - Real-time download progress with resume capability
- 🔒 **Access Control** - Role-based permissions for different user types
- 📱 **Multi-platform Support** - Download information for different platforms
- 🎯 **Auto-delivery** - Automatic delivery of digital products after purchase

## Getting Started

### Prerequisites

- Node.js 18+
- npm, yarn, pnpm, or bun
- Backend API running (Laravel-based with digital downloads support)

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd frontend
   ```

2. **Install dependencies**
   ```bash
   npm install
   # or
   yarn install
   # or
   pnpm install
   # or
   bun install
   ```

3. **Environment Setup**
   Create a `.env.local` file in the root directory:
   ```env
   NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1
   NEXT_PUBLIC_APP_URL=http://localhost:3000
   NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY=your_stripe_key
   ```

4. **Run the development server**
   ```bash
   npm run dev
   # or
   yarn dev
   # or
   pnpm dev
   # or
   bun dev
   ```

5. **Open your browser**
   Navigate to [http://localhost:3000](http://localhost:3000)

## Project Structure

```
frontend/
├── src/
│   ├── app/                    # Next.js App Router pages
│   │   ├── (main)/            # Main site pages
│   │   │   ├── account/       # User account pages
│   │   │   │   └── digital-library/  # Digital downloads library
│   │   │   ├── download/      # Download handler pages
│   │   │   ├── products/      # Product pages
│   │   │   └── cart/          # Shopping cart
│   │   └── (auth)/            # Authentication pages
│   ├── components/            # Reusable React components
│   │   ├── digital/           # Digital download components (NEW!)
│   │   │   ├── DigitalLibrary.tsx
│   │   │   ├── DownloadProgress.tsx
│   │   │   ├── LicenseManager.tsx
│   │   │   └── DigitalProductCard.tsx
│   │   ├── product/           # Product-related components
│   │   ├── cart/              # Shopping cart components
│   │   ├── auth/              # Authentication components
│   │   ├── layout/            # Layout components
│   │   └── ui/                # Base UI components
│   ├── hooks/                 # Custom React hooks
│   │   ├── useDigitalProducts.ts  # Digital downloads hook (NEW!)
│   │   └── useDownloadProgress.ts # Download progress hook (NEW!)
│   ├── stores/                # Zustand state management
│   │   ├── digitalProductsStore.ts  # Digital products state (NEW!)
│   │   ├── cartStore.ts
│   │   └── userStore.ts
│   ├── types/                 # TypeScript type definitions
│   │   ├── digital-products.ts   # Digital download types (NEW!)
│   │   └── product.ts
│   ├── lib/                   # Utility functions
│   └── styles/                # Global styles
├── public/                    # Static assets
└── package.json
```

## Digital Downloads Features

### For Customers

#### Digital Library Dashboard
Navigate to `/account/digital-library` to access:
- 📚 All purchased digital products
- 📊 Download statistics and usage analytics
- 🔑 License key management
- ⏰ Expiration tracking and notifications

#### Secure Downloads
- **Token-based Security**: Each download uses a unique, time-limited token
- **Download Limits**: Configurable download attempts per purchase
- **Progress Tracking**: Real-time download progress with pause/resume
- **Multi-file Support**: Download individual files or complete packages

#### License Management
- **Key Generation**: Automatic license key generation for software products
- **Activation Tracking**: Monitor device activations and usage
- **Validation API**: Real-time license validation for software integration
- **Renewal Notifications**: Automatic alerts for expiring licenses

### API Integration

The frontend integrates with the following API endpoints:

#### Digital Library
```typescript
GET /api/v1/my-digital-products         # User's digital library
GET /api/v1/my-digital-products/licenses # User's license keys
GET /api/v1/my-digital-products/statistics # Usage statistics
```

#### Downloads
```typescript
GET /api/v1/digital/download/{token}    # Secure file download
GET /api/v1/digital/download/{token}/info # Download information
POST /api/v1/digital/download/{token}/progress/{attemptId} # Progress updates
```

#### License Management
```typescript
POST /api/v1/license/validate           # Validate license key
POST /api/v1/license/activate           # Activate license
POST /api/v1/license/deactivate         # Deactivate license
```

## Components Usage

### Digital Library Component

```tsx
import { DigitalLibrary } from '@/components/digital';

export default function LibraryPage() {
  return (
    <DigitalLibrary 
      showStats={true}
      className="max-w-6xl mx-auto"
    />
  );
}
```

### Download Progress Component

```tsx
import { DownloadProgress } from '@/components/digital';

export default function DownloadPage() {
  return (
    <DownloadProgress
      token="download-token-here"
      onComplete={() => console.log('Download complete!')}
      onError={(error) => console.error('Download failed:', error)}
    />
  );
}
```

### License Manager Component

```tsx
import { LicenseManager } from '@/components/digital';

export default function LicensePage() {
  const { licenseKeys } = useDigitalProducts();
  
  return (
    <LicenseManager 
      licenseKeys={licenseKeys}
      className="space-y-4"
    />
  );
}
```

## Hooks Usage

### Digital Products Hook

```tsx
import { useDigitalProducts } from '@/hooks/useDigitalProducts';

export default function MyComponent() {
  const {
    digitalProducts,
    downloadAccesses,
    licenseKeys,
    statistics,
    loading,
    error,
    refreshLibrary,
    downloadFile,
    validateLicense
  } = useDigitalProducts();

  const handleDownload = async (token: string) => {
    try {
      await downloadFile(token);
      await refreshLibrary(); // Refresh to update download counts
    } catch (error) {
      console.error('Download failed:', error);
    }
  };

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;

  return (
    <div>
      {downloadAccesses.map(access => (
        <div key={access.id}>
          <h3>{access.product.name}</h3>
          <button onClick={() => handleDownload(access.access_token)}>
            Download
          </button>
        </div>
      ))}
    </div>
  );
}
```

### Download Progress Hook

```tsx
import { useDownloadProgress } from '@/hooks/useDownloadProgress';

export default function DownloadComponent() {
  const {
    progress,
    status,
    error,
    startDownload,
    cancelDownload,
    retryDownload
  } = useDownloadProgress();

  return (
    <div>
      {status === 'downloading' && (
        <div>
          <div>Progress: {progress}%</div>
          <button onClick={cancelDownload}>Cancel</button>
        </div>
      )}
      {status === 'error' && (
        <div>
          <div>Error: {error}</div>
          <button onClick={retryDownload}>Retry</button>
        </div>
      )}
    </div>
  );
}
```

## State Management

The application uses Zustand for state management with persistence:

```tsx
import { useDigitalProductsStore } from '@/stores/digitalProductsStore';

export default function Component() {
  const {
    digitalProducts,
    downloadAccesses,
    setDownloadAccesses,
    updateDownloadAccess,
    clearCache
  } = useDigitalProductsStore();
  
  // State is automatically persisted to localStorage
}
```

## Routing

### Digital Downloads Routes

- `/account/digital-library` - Main digital library dashboard
- `/download/[token]` - Secure download handler
- `/products/[id]/digital-info` - Digital product information
- `/account/licenses` - License management (can be added as tab in library)

### Protected Routes

All digital download routes require authentication and are protected by the `RouteGuard` component:

```tsx
import { RouteGuard } from '@/components/auth/RouteGuard';

export default function ProtectedPage() {
  return (
    <RouteGuard requireAuth>
      <YourProtectedContent />
    </RouteGuard>
  );
}
```

## TypeScript Types

The application includes comprehensive TypeScript types for digital downloads:

```typescript
interface DigitalProduct {
  id: number;
  name: string;
  product_type: 'digital' | 'hybrid';
  requires_license: boolean;
  supported_platforms: string[];
  latest_version: string;
  // ... more properties
}

interface DownloadAccess {
  id: number;
  access_token: string;
  download_limit: number;
  downloads_remaining: number;
  expires_at: string;
  status: 'active' | 'expired' | 'revoked';
  // ... more properties
}

interface LicenseKey {
  id: number;
  key: string;
  max_activations: number;
  activation_count: number;
  expires_at?: string;
  status: 'active' | 'expired' | 'revoked';
  // ... more properties
}
```

## Security Considerations

### Download Security
- All downloads require authentication tokens
- Tokens have configurable expiration times
- Download attempts are rate-limited and logged
- IP address validation can be enforced
- Download counts are strictly enforced

### License Security
- License keys are generated with cryptographic security
- Activation tracking prevents unauthorized usage
- Real-time validation prevents key sharing
- Device fingerprinting for activation management

## Performance Optimizations

- **Code Splitting**: Digital download components are lazy-loaded
- **Caching**: Download metadata is cached with Zustand persistence
- **Progressive Downloads**: Large files support resume capability
- **Optimistic Updates**: UI updates immediately while syncing with API
- **Background Sync**: Download status updates happen in background

## Testing

```bash
# Run all tests
npm run test

# Run tests in watch mode
npm run test:watch

# Run e2e tests for digital downloads
npm run test:e2e

# Generate coverage report
npm run test:coverage
```

## Building for Production

```bash
# Build the application
npm run build

# Start production server
npm run start

# Analyze bundle size
npm run analyze
```

## Environment Variables

### Required for Digital Downloads

```env
# API Configuration
NEXT_PUBLIC_API_URL=https://your-api-domain.com/api/v1

# Download Configuration
NEXT_PUBLIC_MAX_DOWNLOAD_SIZE=104857600  # 100MB default
NEXT_PUBLIC_DOWNLOAD_TIMEOUT=3600000     # 1 hour timeout

# Security
NEXT_PUBLIC_ENABLE_DOWNLOAD_ANALYTICS=true
NEXT_PUBLIC_ENFORCE_IP_VALIDATION=true
```

## Browser Support

- **Modern Browsers**: Full support for Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **Download Features**: Requires browsers with Blob/File API support
- **Progress Tracking**: Uses modern fetch API with progress events
- **Local Storage**: Required for download state persistence

## Contributing

### Digital Downloads Development

When contributing to digital download features:

1. **Follow TypeScript Patterns**: Use provided interfaces and types
2. **Handle Errors Gracefully**: Always provide fallback UI for failed states
3. **Security First**: Validate all user inputs and API responses
4. **Accessibility**: Ensure download progress is announced to screen readers
5. **Testing**: Write tests for both success and failure scenarios

### Code Style

- Use TypeScript for all new components
- Follow existing component structure and naming
- Include proper error boundaries and loading states
- Document complex download logic with comments

## Troubleshooting

### Common Digital Download Issues

**Downloads Not Starting**
- Check authentication token validity
- Verify API endpoint connectivity
- Ensure download access hasn't expired

**Progress Not Updating**
- Check network connectivity
- Verify progress endpoint is responding
- Check browser console for JavaScript errors

**License Validation Failing**
- Verify license key format
- Check product ID matches
- Ensure license hasn't expired or been revoked

**Library Not Loading**
- Clear localStorage cache: `localStorage.clear()`
- Check API authentication
- Verify user has digital products

### Debug Mode

Enable debug logging:

```env
NEXT_PUBLIC_DEBUG_DOWNLOADS=true
```

This will log detailed information about download attempts and API calls to the browser console.

## Support

For technical support or questions about digital download implementation:

1. Check the [API Documentation](https://docs.your-api-domain.com)
2. Review the [troubleshooting guide](#troubleshooting) above
3. Contact the development team
4. Submit issues to the project repository

## License

This project is licensed under the MIT License - see the LICENSE file for details.

---

**Happy coding! 🚀**

The digital downloads feature provides a complete solution for selling and managing digital products, from secure downloads to license management, all wrapped in a beautiful, user-friendly interface.