/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { ApiService } from '../../service/ApiService';
import { L10n } from '../../utils/L10n';
import { ErrorView, EmptyView } from '../../common/Components';
import { TopBar } from '../../common/TopBar';
import { ListRow, RowBadge, ListSectionHeader } from '../../common/ListRow';

@Entry
@Component
struct AttendancePage {
  @State items: Array<Object> = [];
  @State loading: boolean = true;
  @State errMsg: string = ''; // M4: 加载失败的真实错误文案（空 = 无错误）
  @State isRefreshing: boolean = false; // 刷新进行中
  @State total: number = 0;
  @State page: number = 1;
  private api: ApiService = new ApiService();
  private readonly limit: number = 20;

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
      const res = await this.api.get<Record<string, Object>>(`/admin/hr/attendance?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      this.items = (data['list'] as Array<Object>) ?? [];
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

  /** 标题：嵌套 employee 对象名（controller index with employee；软删后 null 兜底 employee_id） */
  private empTitle(item: Record<string, Object>): string {
    const emp = item['employee'];
    if (emp != null) {
      const e = emp as Record<string, Object>;
      const n = ((e['name'] as string) ?? '').trim();
      const c = ((e['code'] as string) ?? '').trim();
      if (n.length > 0 || c.length > 0) {
        return n.length > 0 ? n : c;
      }
    }
    const raw = item['employee_id'];
    return raw != null ? `#${String(raw)}` : '-';
  }

  /** 状态徽章：DDL 注释 1=正常 2=迟到 3=早退 4=缺卡 5=请假 6=出差 */
  private attBadge(item: Record<string, Object>): RowBadge {
    const code: number = (item['status'] as number) ?? 1;
    let kind: string = 'neutral';
    if (code === 1) {
      kind = 'success';
    } else if (code === 2 || code === 3) {
      kind = 'warning';
    } else if (code === 4) {
      kind = 'danger';
    }
    // 动态资源名（$r 仅支持字面量）：经 L10n 按当前语言解析，缺键回退 key 不崩
    return new RowBadge(kind, L10n.str(this.getUIContext(), `attendance_status_${code}`));
  }

  build() {
    Column() {
      TopBar({
        title: $r('app.string.attendance_list_title'),
        onRefresh: (): void => { this.loadData(true); },
        refreshing: this.loading || this.isRefreshing,
      })

      if (this.loading) {
        LoadingProgress()
      } else if (this.errMsg !== '') {
        // 失败错误位：真实文案 + 重试（ErrorView 内建）
        ErrorView({ message: this.errMsg, onRetry: (): void => { this.loadData(); } })
      } else if (this.items.length === 0) {
        EmptyView();
      } else {
        // 3.0 卡层级：surface 列表卡（内容头行 + 行列表）悬浮 bg_page，圆角 + 发丝边框
        Column() {
          Column() {
            ListSectionHeader({ moduleKey: 'hr', count: this.total })

            Refresh({ refreshing: $$this.isRefreshing }) {
              List() {
                ForEach(this.items, (item: Record<string, Object>, index: number) => {
                  ListItem() {
                    ListRow({
                      // HrAttendance 实列无 name/code：标题=员工（with employee 嵌套），副行=工作日
                      title: this.empTitle(item),
                      subtitle: ((item['work_date'] as string) ?? '') as string,
                      icon: this.empTitle(item),
                      badge: this.attBadge(item),
                      isLast: index === this.items.length - 1
                    })
                  }
                })
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
