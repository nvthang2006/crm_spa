export type UpdateArtifact = {
  version: string
  download: () => Promise<void>
  install: () => Promise<void>
  close: () => Promise<void>
}

type UpdaterState = {
  update: UpdateArtifact | null
  downloaded: boolean
  busy: boolean
  dismissed: boolean
}

export class DesktopUpdater {
  private state: UpdaterState = { update: null, downloaded: false, busy: false, dismissed: false }
  private listeners = new Set<() => void>()
  private generation = 0
  private checkUpdate: () => Promise<UpdateArtifact | null>

  constructor(checkUpdate: () => Promise<UpdateArtifact | null>) {
    this.checkUpdate = checkUpdate
  }

  getSnapshot = (): UpdaterState => this.state

  subscribe = (listener: () => void): (() => void) => {
    this.listeners.add(listener)
    return () => { this.listeners.delete(listener) }
  }

  private setState(patch: Partial<UpdaterState>): void {
    this.state = { ...this.state, ...patch }
    this.listeners.forEach((listener) => listener())
  }

  private close(update: UpdateArtifact | null): void {
    void update?.close().catch(() => undefined)
  }

  reset = (): void => {
    this.generation += 1
    this.close(this.state.update)
    this.setState({ update: null, downloaded: false, busy: false, dismissed: false })
  }

  dismiss = (): void => { this.setState({ dismissed: true }) }

  async check(): Promise<UpdateArtifact | null | undefined> {
    if (this.state.busy) return undefined
    const generation = this.generation
    this.setState({ busy: true })
    try {
      const update = await this.checkUpdate()
      if (generation !== this.generation) {
        this.close(update)
        return undefined
      }
      if (update && this.state.update?.version === update.version && this.state.downloaded) {
        this.close(update)
        this.setState({ dismissed: false })
        return this.state.update
      }
      this.close(this.state.update)
      this.setState({ update, downloaded: false, dismissed: false })
      return update
    } finally {
      if (generation === this.generation) this.setState({ busy: false })
    }
  }

  async download(): Promise<boolean> {
    const { update, busy, downloaded } = this.state
    if (!update || busy || downloaded) return false
    const generation = this.generation
    this.setState({ busy: true })
    try {
      await update.download()
      if (generation !== this.generation) {
        this.close(update)
        return false
      }
      this.setState({ downloaded: true })
      return true
    } finally {
      if (generation === this.generation) this.setState({ busy: false })
    }
  }

  async install(isBlocked: () => boolean): Promise<boolean> {
    const { update, busy, downloaded } = this.state
    if (!update || busy || !downloaded || isBlocked()) return false
    const generation = this.generation
    this.setState({ busy: true })
    try {
      await update.install()
      return generation === this.generation
    } finally {
      if (generation === this.generation) this.setState({ busy: false })
    }
  }
}
