declare module '@inertiajs/react' {
  export function usePage<T>(): { props: T };
}

declare module 'react' {
  export function useMemo<T>(factory: () => T, dependencies: readonly unknown[]): T;
}
