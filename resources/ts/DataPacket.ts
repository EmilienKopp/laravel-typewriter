export interface DataPacketInit<TData extends Record<string, unknown> = Record<string, unknown>> {
    data: TData;
    meta?: Record<string, unknown>;
    defaults?: Partial<TData>;
    default?: unknown;
    source?: string | number | null;
    target?: string | number | null;
}

export class DataPacket<TData extends Record<string, unknown> = Record<string, unknown>> {
    readonly data: TData;
    readonly meta: Record<string, unknown>;
    readonly defaults: Partial<TData>;
    readonly default: unknown;
    readonly source: string | number | null;
    readonly target: string | number | null;

    constructor(init: DataPacketInit<TData>) {
        this.data = init.data;
        this.meta = init.meta ?? {};
        this.defaults = DataPacket.#filterDefaults(init.data, init.defaults ?? {});
        this.default = init.default ?? null;
        this.source = init.source ?? null;
        this.target = init.target ?? null;
    }

    /**
     * Deserialize a plain object (e.g. from an Inertia prop) into a DataPacket.
     */
    static from<TData extends Record<string, unknown>>(
        raw: DataPacketInit<TData>,
    ): DataPacket<TData> {
        return new DataPacket(raw);
    }

    /**
     * Get a value from `data`, falling back to `defaults[key]` if the value is nullish.
     */
    get<K extends keyof TData>(key: K): TData[K] | undefined {
        return (this.data[key] ?? this.defaults[key]) as TData[K] | undefined;
    }

    /**
     * Get the raw value from `data` with no default resolution.
     */
    raw<K extends keyof TData>(key: K): TData[K] | undefined {
        return this.data[key];
    }

    static data<TData extends Record<string, unknown>>(raw: DataPacketInit<TData>): TData {
        return raw.data;
    }

    static defaults<TData extends Record<string, unknown>>(
        raw: DataPacketInit<TData>,
    ): Partial<TData> {
        return DataPacket.#filterDefaults(raw.data, raw.defaults ?? {});
    }

    static default<TData extends Record<string, unknown>>(raw: DataPacketInit<TData>): unknown {
        return raw.default ?? null;
    }

    static meta<TData extends Record<string, unknown>>(
        raw: DataPacketInit<TData>,
    ): Record<string, unknown> {
        return raw.meta ?? {};
    }

    /**
     * Get a metadata value by key.
     */
    getMeta<T = unknown>(key: string): T | undefined {
        return this.meta[key] as T | undefined;
    }

    hasMeta(key: string): boolean {
        return key in this.meta;
    }

    /**
     * The global fallback (`default` field) — used when the whole packet has no specific match.
     */
    fallback<T = unknown>(): T | null {
        return this.default as T | null;
    }

    isFrom(source: string | number): boolean {
        return this.source === source;
    }

    isFor(target: string | number): boolean {
        return this.target === target;
    }

    toObject(): DataPacketInit<TData> {
        return {
            data: this.data,
            meta: this.meta,
            defaults: this.defaults,
            default: this.default,
            source: this.source,
            target: this.target,
        };
    }

    /** Mirror PHP validate(): strip keys from defaults that don't exist in data. */
    static #filterDefaults<TData extends Record<string, unknown>>(
        data: TData,
        defaults: Partial<TData>,
    ): Partial<TData> {
        return Object.fromEntries(
            Object.entries(defaults).filter(([key]) => key in data),
        ) as Partial<TData>;
    }
}
