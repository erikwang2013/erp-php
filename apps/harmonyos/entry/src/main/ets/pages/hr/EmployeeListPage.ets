/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { ApiService } from '../../service/ApiService';
import { L10n } from '../../utils/L10n';
import { LoadingView, ErrorView, EmptyView } from '../../common/Components';
import { TopBar, TopBarAction } from '../../common/TopBar';
import { ConfirmDialog } from '../../common/FormDialog';
import { ListRow, RowBadge, RowAction, ListSectionHeader } from '../../common/ListRow';
import { EmployeeFormView } from './EmployeeFormView';

/**
 * 员工列表项（与后端 /admin/hr/employee 返回字段对齐）
 */
interface HrEmployeeItem {
  id: string;
  code: string;
  name: string;
  department_id: number;
  position_id: number;
  gender: number;
  phone: string;
  email: string;
  hire_date: string;
  status: number;
}

/** 表单保存请求体（与后端 fillable 对齐） */
interface SaveBody {
  name: string | number;
  code: string | number;
  department_id: string | number;
  position_id: string | number;
  gender: string | number;
  phone: string | number;
  email: string | number;
  hire_date: string | number;
  status: string | number;
}

@Entry
@Component
struct EmployeeListPage {
  // 列表状态
  @State items: Array<HrEmployeeItem> = [];
  @State loading: boolean = true;
  @State total: number = 0;
  @State page: number = 1;
  @State hasError: boolean = false;
  @State errorMsg: string = ''; // M4: 失败的真实错误文案（传给 ErrorView/提示）
  @State isRefreshing: boolean = false;
  private readonly limit: number = 20;
  private api: ApiService = new ApiService();

  // 表单状态
  @State showForm: boolean = false;
  @State formMode: string = 'create'; // create | edit
  @State formId: string = '';
  @State isSubmitting: boolean = false;

  // 表单字段（与后端 fillable 字段名严格一致）
  @State formName: string = '';
  @State formCode: string = '';
  @State formDepartmentId: string = '';
  @State formPositionId: string = '';
  @State formGender: string = '';
  @State formPhone: string = '';
  @State formEmail: string = '';
  @State formHireDate: string = '';
  @State formStatus: number = 1;

  aboutToAppear(): void {
    this.loadData();
  }

  async loadData(isRefresh: boolean = false): Promise<void> {
    if (isRefresh) {
      this.page = 1;
      this.isRefreshing = true;
    } else {
      this.loading = true;
    }
    this.hasError = false;
    this.errorMsg = '';

    try {
      const res = await this.api.get<Record<string, Object>>(`/admin/hr/employee?page=${this.page}&limit=${this.limit}`);
      const data = res.data as Record<string, Object>;
      const list = (data['list'] as Array<Object>) ?? [];
      const mapped = list.map((item: Object) => item as HrEmployeeItem);
      if (isRefresh || this.page === 1) {
        this.items = mapped;
      } else {
        this.items = this.items.concat(mapped);
      }
      this.total = (data['total'] as number) ?? 0;
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      this.hasError = true;
      // M4: 展示真实错误信息（替代固定文案）；UNAUTHORIZED 已在上方跳转
      this.errorMsg = (e instanceof Error && e.message) ? e.message : L10n.str(this.getUIContext(), 'load_failed');
      promptAction.showToast({ message: this.errorMsg });
    } finally {
      this.loading = false;
      this.isRefreshing = false;
    }
  }

  loadMore(): void {
    if (this.items.length >= this.total) return;
    this.page++;
    this.loadData();
  }

  /** 行徽章：在职 success / 离职 danger（kind 同源驱动行 icon 圆徽色） */
  private empBadge(item: HrEmployeeItem): RowBadge {
    if (item.status === 1) {
      return new RowBadge('success', $r('app.string.employee_status_active'));
    }
    return new RowBadge('danger', $r('app.string.employee_status_resigned'));
  }

  /**
   * 打开新建表单（清空字段）
   */
  openCreate(): void {
    this.formMode = 'create';
    this.formId = '';
    this.formName = '';
    this.formCode = '';
    this.formDepartmentId = '';
    this.formPositionId = '';
    this.formGender = '';
    this.formPhone = '';
    this.formEmail = '';
    this.formHireDate = '';
    this.formStatus = 1;
    this.showForm = true;
  }

  /**
   * 打开编辑表单：先 GET 详情回填
   */
  async openEdit(id: string): Promise<void> {
    this.formMode = 'edit';
    this.formId = id;
    this.showForm = true;
    this.loading = true;
    try {
      const res = await this.api.get<Record<string, Object>>(`/admin/hr/employee/${id}`);
      const row = res.data as Record<string, Object>;
      this.formName = (row['name'] as string) ?? '';
      this.formCode = (row['code'] as string) ?? '';
      this.formDepartmentId = row['department_id'] != null ? String(row['department_id']) : '';
      this.formPositionId = row['position_id'] != null ? String(row['position_id']) : '';
      this.formGender = row['gender'] != null ? String(row['gender']) : '';
      this.formPhone = (row['phone'] as string) ?? '';
      this.formEmail = (row['email'] as string) ?? '';
      this.formHireDate = (row['hire_date'] as string) ?? '';
      this.formStatus = (row['status'] as number) ?? 1;
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      // M4: 优先展示真实错误信息
      promptAction.showToast({ message: (e instanceof Error && e.message) ? e.message : L10n.str(this.getUIContext(), 'load_detail_failed') });
    } finally {
      this.loading = false;
    }
  }

  /**
   * 保存：新建 POST /admin/hr/employee，编辑 PUT /admin/hr/employee/{id}
   */
  async save(): Promise<void> {
    if (!this.formCode.trim()) {
      promptAction.showToast({ message: $r('app.string.employee_code_required') });
      return;
    }
    if (!this.formName.trim()) {
      promptAction.showToast({ message: $r('app.string.employee_name_required') });
      return;
    }

    this.isSubmitting = true;
    const body: SaveBody = {
      name: this.formName,
      code: this.formCode,
      department_id: this.formDepartmentId ? Number(this.formDepartmentId) : 0,
      position_id: this.formPositionId ? Number(this.formPositionId) : 0,
      gender: this.formGender ? Number(this.formGender) : 0,
      phone: this.formPhone,
      email: this.formEmail,
      hire_date: this.formHireDate,
      status: this.formStatus
    };

    try {
      if (this.formMode === 'create') {
        await this.api.post<Object>('/admin/hr/employee', body);
        promptAction.showToast({ message: $r('app.string.create_success') });
      } else {
        await this.api.put<Object>(`/admin/hr/employee/${this.formId}`, body);
        promptAction.showToast({ message: $r('app.string.update_success') });
      }
      this.showForm = false;
      // 刷新列表（回到第一页）
      this.loadData(true);
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      // M4: 优先展示真实错误信息
      promptAction.showToast({ message: (e instanceof Error && e.message) ? e.message : L10n.str(this.getUIContext(), 'save_failed') });
    } finally {
      this.isSubmitting = false;
    }
  }

  private dialogController?: CustomDialogController;

  /**
   * 删除入口：行内删除先经 ConfirmDialog 确认（骨架收敛）
   */
  confirmDelete(id: string): void {
    this.dialogController = new CustomDialogController({
      builder: ConfirmDialog({
        title: $r('app.string.delete_confirm'),
        message: $r('app.string.employee_delete_msg'),
        onConfirm: () => {
          this.deleteItem(id);
        },
      }),
      alignment: DialogAlignment.Center,
      autoCancel: true,
    });
    this.dialogController.open();
  }

  /**
   * 删除：DELETE /admin/hr/employee/{id}
   */
  async deleteItem(id: string): Promise<void> {
    try {
      await this.api.delete<Object>(`/admin/hr/employee/${id}`);
      promptAction.showToast({ message: $r('app.string.delete_success') });
      this.loadData(true);
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      // M4: 优先展示真实错误信息
      promptAction.showToast({ message: (e instanceof Error && e.message) ? e.message : L10n.str(this.getUIContext(), 'delete_failed') });
    }
  }

  build() {
    Column() {
      // 顶栏（骨架收敛：TopBar 替代手写行，docs/mobile-ui-design.md 5.1）
      TopBar({
        title: $r('app.string.employee_list_title'),
        actions: [new TopBarAction($r('app.string.user_add'))],
        onAction: () => this.openCreate(),
        onRefresh: () => this.loadData(true),
        refreshing: this.isRefreshing || this.loading,
      })

      if (this.showForm) {
        this.buildForm()
      } else {
        this.buildList()
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor($r('app.color.bg_page'))
  }

  /**
   * 列表视图（保留原有分页逻辑）
   */
  @Builder
  buildList() {
    if (this.loading && this.items.length === 0) {
      LoadingView();
    } else if (this.hasError) {
      ErrorView({ message: this.errorMsg, onRetry: (): void => { this.loadData(true); } });
    } else if (this.items.length === 0) {
      EmptyView({ message: $r('app.string.employee_empty') });
    } else {
      // 3.0 卡层级：surface 列表卡（内容头行 + 行列表）悬浮 bg_page，圆角 + 发丝边框
      Column() {
        Column() {
          ListSectionHeader({ moduleKey: 'hr', count: this.total })

          Refresh({ refreshing: $$this.isRefreshing }) {
            List() {
              ForEach(this.items, (item: HrEmployeeItem, index: number) => {
                ListItem() {
                  ListRow({
                    title: item.name || item.code || '-',
                    subtitle: L10n.str(this.getUIContext(), 'employee_department_fmt',
                      item.department_id != null ? String(item.department_id) : '-'),
                    icon: item.name || item.code || '-',
                    badge: this.empBadge(item),
                    actions: [
                      new RowAction($r('app.string.edit'), 'primary', (): void => {
                        this.openEdit(item.id);
                      }),
                      new RowAction($r('app.string.delete'), 'danger', (): void => {
                        this.confirmDelete(item.id);
                      })
                    ],
                    isLast: index === this.items.length - 1
                  })
                }
              }, (item: HrEmployeeItem) => item.id)

              // 加载更多
              ListItem() {
                Row() {
                  if (this.items.length >= this.total) {
                    Text('— ' + L10n.str(this.getUIContext(), 'no_more') + ' —')
                      .fontSize(12)
                      .fontColor($r('app.color.text_disabled'))
                  } else {
                    Button($r('app.string.load_more'))
                      .fontSize(13)
                      .fontColor($r('app.color.primary'))
                      .backgroundColor(Color.Transparent)
                      .onClick(() => this.loadMore())
                  }
                }
                .justifyContent(FlexAlign.Center)
                .width('100%')
                .height(48)
              }
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

  /**
   * 表单视图（新建/编辑共用）：渲染抽到 EmployeeFormView，父页保留字段状态与保存逻辑
   */
  @Builder
  buildForm() {
    EmployeeFormView({
      formMode: this.formMode,
      isSubmitting: this.isSubmitting,
      formName: this.formName,
      formCode: this.formCode,
      formDepartmentId: this.formDepartmentId,
      formPositionId: this.formPositionId,
      formGender: this.formGender,
      formPhone: this.formPhone,
      formEmail: this.formEmail,
      formHireDate: this.formHireDate,
      formStatus: this.formStatus,
      onSubmit: () => { this.save(); },
      onCancel: () => { this.showForm = false; }
    })
  }
}
