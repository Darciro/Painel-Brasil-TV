import { existsSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

const hotFile = resolve( import.meta.dirname, 'hot' );

/**
 * Writes a `hot` file with the dev server URL while `vite dev` is running,
 * so functions.php can detect dev mode and load assets from the dev server
 * instead of the built manifest.
 */
function pbtvHotFile() {
	const cleanup = () => {
		if ( existsSync( hotFile ) ) {
			rmSync( hotFile );
		}
	};

	return {
		name: 'pbtv-hot-file',
		configureServer( server ) {
			server.httpServer?.once( 'listening', () => {
				const address = server.httpServer.address();
				writeFileSync( hotFile, `http://localhost:${ address.port }` );
			} );

			server.httpServer?.once( 'close', cleanup );
			process.once( 'exit', cleanup );
		},
	};
}

export default defineConfig( ( { command } ) => {
	// A `vite build` must always force production mode, even if a previous
	// `vite dev` run left a stale `hot` file behind (e.g. the dev server
	// was killed without going through its normal shutdown).
	if ( command === 'build' && existsSync( hotFile ) ) {
		rmSync( hotFile );
	}

	return {
		plugins: [ tailwindcss(), pbtvHotFile() ],
		server: {
			// The app is served from a different origin (e.g. https://painelbrasil.tv.test),
			// so the dev server must send CORS headers for its assets to load.
			cors: true,
			strictPort: true,
			origin: 'http://localhost:5173',
		},
		build: {
			outDir: 'assets/dist',
			manifest: true,
			emptyOutDir: true,
			rollupOptions: {
				input: [ 'src/css/main.css' ],
			},
		},
	};
} );
