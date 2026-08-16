/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import router from '@ohos.router';
import promptAction from '@ohos.promptAction';
import { ApiService } from '../../service/ApiService';
import { LoadingView, ErrorView, EmptyView } from '../../common/Components';

/**
 * 部门列表项（与后端 /admin/hr/department 返回字段对齐）
 */
interface HrDepartmentItem {
  id: string;
  name: string;
  code: string;
  status: number;
}

@Entry
@Component
struct DepartmentPage {
  // 列表状态
  @State items: Array<HrDepartmentItem> = [];
  @State loading: boolean = true;
  @State total: number = 0;
  @State page: number = 1;
  @State hasError: boolean = false;
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
  @State formParentId: string = '';
  @State formManagerUserId: string = '';
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

    try {
      const res = await this.api.get(`/admin/hr/department?page=${this.page}&limit=${this.limit}`);
      const data = res['data'] as Record<string, Object>;
      const list = (data['list'] as Array<Object>) ?? [];
      const mapped = list.map((item: Object) => item as HrDepartmentItem);
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
      promptAction.showToast({ message: '加载失败' });
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

  /**
   * 打开新建表单（清空字段）
   */
  openCreate(): void {
    this.formMode = 'create';
    this.formId = '';
    this.formName = '';
    this.formCode = '';
    this.formParentId = '';
    this.formManagerUserId = '';
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
      const res = await this.api.get(`/admin/hr/department/${id}`);
      const row = res['data'] as Record<string, Object>;
      this.formName = (row['name'] as string) ?? '';
      this.formCode = (row['code'] as string) ?? '';
      this.formParentId = row['parent_id'] != null ? String(row['parent_id']) : '';
      this.formManagerUserId = row['manager_user_id'] != null ? String(row['manager_user_id']) : '';
      this.formStatus = (row['status'] as number) ?? 1;
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: '加载详情失败' });
    } finally {
      this.loading = false;
    }
  }

  /**
   * 保存：新建 POST /admin/hr/department，编辑 PUT /admin/hr/department/{id}
   */
  async save(): Promise<void> {
    if (!this.formName.trim()) {
      promptAction.showToast({ message: '请输入部门名称' });
      return;
    }
    if (!this.formCode.trim()) {
      promptAction.showToast({ message: '请输入部门编码' });
      return;
    }

    this.isSubmitting = true;
    const body: Record<string, Object> = {
      name: this.formName,
      code: this.formCode,
      parent_id: this.formParentId ? Number(this.formParentId) : 0,
      manager_user_id: this.formManagerUserId ? Number(this.formManagerUserId) : 0,
      status: this.formStatus
    };

    try {
      if (this.formMode === 'create') {
        await this.api.post('/admin/hr/department', body);
        promptAction.showToast({ message: '创建成功' });
      } else {
        await this.api.put(`/admin/hr/department/${this.formId}`, body);
        promptAction.showToast({ message: '更新成功' });
      }
      this.showForm = false;
      // 刷新列表（回到第一页）
      this.loadData(true);
    } catch (e) {
      if (e instanceof Error && e.message === 'UNAUTHORIZED') {
        router.replaceUrl({ url: 'pages/LoginPage' });
        return;
      }
      promptAction.showToast({ message: '保存失败' });
    } finally {
      this.isSubmitting = false;
    }
  }

  /**
   * 删除：确认对话框后 DELETE /admin/hr/department/{id}
   */
  deleteItem(id: string): void {
    AlertDialog.show({
      title: '确认删除',
      message: '确定要删除该部门吗？删除后不可恢复。',
      autoCancel: true,
      alignment: DialogAlignment.Center,
      primaryButton: {
        value: '确定',
        fontColor: '#FF4D4F',
        action: async () => {
          try {
            await this.api.delete(`/admin/hr/department/${id}`);
            promptAction.showToast({ message: '删除成功' });
            this.loadData(true);
          } catch (e) {
            if (e instanceof Error && e.message === 'UNAUTHORIZED') {
              router.replaceUrl({ url: 'pages/LoginPage' });
              return;
            }
            promptAction.showToast({ message: '删除失败' });
          }
        }
      },
      secondaryButton: {
        value: '取消',
        action: () => {}
      }
    });
  }

  build() {
    Column() {
      // 顶栏
      Row() {
        Button({ type: ButtonType.Circle }) {
          Image($rawfile('back.png')).width(24).height(24)
        }
        .width(36).height(36)
        .backgroundColor('#FFFFFF')
        .onClick(() => router.back())

        Text('部门管理')
          .fontSize(18)
          .fontWeight(FontWeight.Bold)
          .margin({ left: 8 })

        Blank()

        Button('+ 新增')
          .fontSize(13)
          .fontColor('#FFFFFF')
          .backgroundColor('#1677FF')
          .borderRadius(4)
          .height(32)
          .onClick(() => this.openCreate())
      }
      .width('100%')
      .height(56)
      .padding({ left: 16, right: 16 })
      .backgroundColor('#FFFFFF')

      if (this.showForm) {
        this.buildForm()
      } else {
        this.buildList()
      }
    }
    .width('100%')
    .height('100%')
    .backgroundColor('#F5F5F5')
  }

  /**
   * 列表视图（保留原有分页逻辑）
   */
  @Builder
  buildList() {
    if (this.loading && this.items.length === 0) {
      LoadingView();
    } else if (this.hasError) {
      ErrorView({ onRetry: () => this.loadData(true) });
    } else if (this.items.length === 0) {
      EmptyView({ message: '暂无部门数据' });
    } else {
      Refresh({ refreshing: $$this.isRefreshing }) {
        List() {
          ForEach(this.items, (item: HrDepartmentItem) => {
            ListItem() {
              Row() {
                // 名称与编码
                Column() {
                  Text(item.name || item.code || '-')
                    .fontSize(15)
                    .fontWeight(FontWeight.Medium)
                    .maxLines(1)
                    .textOverflow({ overflow: TextOverflow.Ellipsis })

                  Text(`编码: ${item.code || '-'}`)
                    .fontSize(12)
                    .fontColor('#999999')
                    .margin({ top: 2 })
                    .maxLines(1)
                    .textOverflow({ overflow: TextOverflow.Ellipsis })
                }
                .alignItems(HorizontalAlign.Start)
                .layoutWeight(1)

                // 状态
                Text(item.status === 1 ? '启用' : '禁用')
                  .fontSize(10)
                  .fontColor(item.status === 1 ? '#52C41A' : '#FF4D4F')
                  .backgroundColor(item.status === 1 ? '#E6FFF0' : '#FFE6E6')
                  .borderRadius(2)
                  .padding({ left: 6, right: 6, top: 2, bottom: 2 })

                // 操作
                Button('编辑')
                  .fontSize(12)
                  .fontColor('#1677FF')
                  .backgroundColor('#E6F0FF')
                  .borderRadius(4)
                  .height(28)
                  .margin({ left: 8 })
                  .onClick(() => this.openEdit(item.id))

                Button('删除')
                  .fontSize(12)
                  .fontColor('#FF4D4F')
                  .backgroundColor('#FFE6E6')
                  .borderRadius(4)
                  .height(28)
                  .margin({ left: 8 })
                  .onClick(() => this.deleteItem(item.id))
              }
              .width('100%')
              .padding({ left: 16, right: 16, top: 10, bottom: 10 })
              .backgroundColor('#FFFFFF')
              .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
            }
          }, (item: HrDepartmentItem) => item.id)

          // 加载更多
          ListItem() {
            Row() {
              if (this.items.length >= this.total) {
                Text('— 没有更多了 —')
                  .fontSize(12)
                  .fontColor('#CCCCCC')
              } else {
                Button('加载更多')
                  .fontSize(13)
                  .fontColor('#1677FF')
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
  }

  /**
   * 表单视图（新建/编辑共用）
   */
  @Builder
  buildForm() {
    Scroll() {
      Column() {
        Text(this.formMode === 'create' ? '新增部门' : '编辑部门')
          .fontSize(16)
          .fontWeight(FontWeight.Bold)
          .width('100%')
          .textAlign(TextAlign.Center)
          .padding({ top: 16, bottom: 16 })

        this.buildFormItem('部门名称', () => {
          TextInput({ placeholder: '请输入部门名称（必填）', text: this.formName })
            .fontSize(15)
            .onChange((v: string) => { this.formName = v; })
        });

        this.buildFormItem('部门编码', () => {
          TextInput({ placeholder: '请输入部门编码（必填）', text: this.formCode })
            .fontSize(15)
            .onChange((v: string) => { this.formCode = v; })
        });

        this.buildFormItem('上级部门ID', () => {
          TextInput({ placeholder: '上级部门数字ID，留空为0', text: this.formParentId })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formParentId = v; })
        });

        this.buildFormItem('负责人ID', () => {
          TextInput({ placeholder: '负责人用户数字ID，留空为0', text: this.formManagerUserId })
            .fontSize(15)
            .type(InputType.Number)
            .onChange((v: string) => { this.formManagerUserId = v; })
        });

        this.buildFormItem('状态', () => {
          Row() {
            Button('启用')
              .fontSize(13)
              .fontColor(this.formStatus === 1 ? '#FFFFFF' : '#666666')
              .backgroundColor(this.formStatus === 1 ? '#52C41A' : '#F5F5F5')
              .borderRadius(4)
              .height(32)
              .onClick(() => { this.formStatus = 1; })

            Button('禁用')
              .fontSize(13)
              .fontColor(this.formStatus === 0 ? '#FFFFFF' : '#666666')
              .backgroundColor(this.formStatus === 0 ? '#FF4D4F' : '#F5F5F5')
              .borderRadius(4)
              .height(32)
              .margin({ left: 12 })
              .onClick(() => { this.formStatus = 0; })
          }
        });

        // 操作按钮
        Row() {
          Button('取 消')
            .layoutWeight(1)
            .height(44)
            .fontSize(15)
            .fontColor('#666666')
            .backgroundColor('#F0F0F0')
            .borderRadius(8)
            .onClick(() => { this.showForm = false; })

          Button('保 存')
            .layoutWeight(1)
            .height(44)
            .fontSize(15)
            .fontColor('#FFFFFF')
            .backgroundColor(this.isSubmitting ? '#99BBFF' : '#1677FF')
            .borderRadius(8)
            .margin({ left: 16 })
            .enabled(!this.isSubmitting)
            .onClick(() => { this.save(); })
        }
        .width('100%')
        .padding({ left: 16, right: 16, top: 32, bottom: 32 })
      }
      .width('100%')
    }
    .width('100%')
    .layoutWeight(1)
    .backgroundColor('#F5F5F5')
  }

  /**
   * 表单项：标签 + 输入控件
   */
  @Builder
  buildFormItem(label: string, content: () => void) {
    Row() {
      Text(label)
        .fontSize(15)
        .fontColor('#333333')
        .width(80)

      content()
        .layoutWeight(1)
    }
    .width('100%')
    .minHeight(56)
    .padding({ left: 16, right: 16 })
    .backgroundColor('#FFFFFF')
    .border({ width: { bottom: 0.5 }, color: '#EEEEEE' })
    .alignItems(VerticalAlign.Center)
  }
}
