/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import promptAction from '@ohos.promptAction';
import { ApiService } from '../../service/ApiService';
import { L10n } from '../../utils/L10n';
import { ErrorView } from '../../common/Components';
import { TopBar } from '../../common/TopBar';
import { ConfirmDialog } from '../../common/FormDialog';
import { ListRow, RowBadge, RowAction, ListSectionHeader } from '../../common/ListRow';

/** POST body：开始拣货零字段（ArkTS 禁裸对象字面量传参） */
class EmptyBody {
}

/** 拣货任务行（与 /admin/wms/pick 列表字段对齐：无 join，仅任务表自身列） */

interface PickRow {
  id: string;
  code: string;
  status: number;
  started_at: string;
}

@Entry
@Component
struct PickPage {
  @State items: Array<PickRow> = [];
  @State loading: boolean = true;
  @State errMsg: string = ''; // M4: 加载失败的真实错误文案（空 = 无错误）
  @State isRefreshing: boolean = false; // 刷新进行中
  @State total: number = 0;
  @State page: number = 1;
  private api: ApiService = new ApiService();
  private readonly limit: number = 20;
  dialogController: CustomDialogController | null = null;

  aboutToAppear() {
    this.loadData();
  }

  async loadData(isRefresh: boolean = false) {
    if (isRefresh) {
      this.page = 1;
      this.isRefreshing = true;
    } else {
      this.loading = true;
    }
    this.errMsg = '';
    try {
      const res = await this.api.get<Record<string, Object>>(`/admin/wms/pick?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      this.items = ((data['list'] as Array<Object>) ?? []).map((it: Object) => it as PickRow);
      this.total = (data['total'] as number) ?? 0;
    } catch (e) {
      // M4: 错误不再吞掉——真实 message 优先展示，空信息/UNAUTHORIZED 给友好提示
      const msg = e instanceof Error ? e.message : '';
      this.errMsg = (msg === '' || msg === 'UNAUTHORIZED') ? L10n.str(this.getUIContext(), 'login_expired') : msg;
    } finally {
      this.loading = false;
      this.isRefreshing = false;
    }
  }

  /** 行徽章：0 待拣货/1 拣货中 warning（0 行内可开始拣货）、2 已完成 success、3 已取消 danger */
  private badge(item: PickRow): RowBadge {
    const code: number = item.status;
    let kind: string = 'neutral';
    if (code === 0 || code === 1) {
      kind = 'warning';
    } else if (code === 2) {
      kind = 'success';
    } else if (code === 3) {
      kind = 'danger';
    }
    return new RowBadge(kind, L10n.str(this.getUIContext(), `pick_status_${code}`));
  }

  /** 开始拣货：ConfirmDialog → POST /admin/wms/pick/{id}/start（后端校验 status===0，零 body） */
  confirmStart(id: string): void {
    this.dialogController = new CustomDialogController({
      builder: ConfirmDialog({
        title: $r('app.string.pick_start'),
        message: $r('app.string.pick_confirm_start'),
        onConfirm: () => {
          this.startPick(id);
        },
      }),
      alignment: DialogAlignment.Center,
      autoCancel: true,
    });
    this.dialogController.open();
  }

  async startPick(id: string) {
    try {
      await this.api.post<Object>(`/admin/wms/pick/${id}/start`, new EmptyBody());
      promptAction.showToast({ message: $r('app.string.op_success') });
      this.loadData(true);
    } catch (e) {
      const msg = e instanceof Error ? e.message : '';
      promptAction.showToast({ message: msg.length > 0 && msg !== 'UNAUTHORIZED' ? msg : L10n.str(this.getUIContext(), 'op_failed') });
    }
  }

  build() {
    Column() {
      TopBar({
        title: $r('app.string.pick_list_title'),
        onRefresh: (): void => { this.loadData(true); },
        refreshing: this.loading || this.isRefreshing,
      })

      if (this.loading) {
        LoadingProgress()
      } else if (this.errMsg !== '') {
        // 失败错误位：真实文案 + 重试（ErrorView 内建）
        ErrorView({ message: this.errMsg, onRetry: (): void => { this.loadData(); } })
      } else {
        // 3.0 卡层级：surface 列表卡（内容头行 + 行列表）悬浮 bg_page，圆角 + 发丝边框
        Column() {
          Column() {
            ListSectionHeader({ moduleKey: 'wms', count: this.total })

            Refresh({ refreshing: $$this.isRefreshing }) {
              List() {
                ForEach(this.items, (item: PickRow, index: number) => {
                  ListItem() {
                    ListRow({
                      title: item.code || item.id || '-',
                      subtitle: item.started_at || '',
                      icon: item.code || item.id || '-',
                      badge: this.badge(item),
                      actions: item.status === 0 ? [
                        new RowAction($r('app.string.pick_start'), 'primary', (): void => {
                          this.confirmStart(item.id);
                        })
                      ] : [],
                      isLast: index === this.items.length - 1
                    })
                  }
                }, (item: PickRow) => item.id)
              }
              .width('100%')
              .layoutWeight(1)
              .edgeEffect(EdgeEffect.Spring)
            }
            .onRefreshing(() => {
              this.loadData(true);
            })
          }
          .width('100%')
          .layoutWeight(1)
          .borderRadius(8)
          .clip(true)
          .backgroundColor($r('app.color.surface'))
          .border({ width: 1, color: $r('app.color.border') })
        }
        .width('100%')
        .layoutWeight(1)
        .padding({ left: 12, right: 12, top: 12, bottom: 12 })
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor($r('app.color.bg_page'))
  }
}
