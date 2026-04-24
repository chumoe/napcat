<?php

namespace Chumoe\Napcat;

use support\Cache;

/**
 * Napcat类 - 实现单例模式的消息处理类
 */
class Napcat
{
    /**
     * @var Napcat|null 单例模式的实例变量
     */
    private static ?Napcat $instance = null;

    /**
     * 私有构造函数，防止外部直接实例化
     */
    private function __construct()
    {
    }

    /**
     * 获取单例实例
     * @return Napcat|null 返回单例实例，如果实例不存在则创建
     */
    public static function getInstance(): ?Napcat
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 检查napcat运行状态
     * @return bool 返回napcat的运行状态，true表示正在运行，false表示未运行
     */
    public function status(): bool
    {
        return Cache::get('napcat.run', false);
    }

    /**
     * 处理消息数据，如果echo长度超过16则缓存
     * @param array $data 包含消息数据的数组
     */
    public function message(array $data): void
    {
        if (strlen($data['echo']) > 16) Cache::set($data['echo'], $data, 60);
    }

    /**
     * 基础方法，用于处理队列操作
     * @param string $action 执行的操作名称
     * @param array $params 操作所需的参数数组
     * @param bool $wait 是否等待操作结果
     * @return mixed 返回操作结果，如果不需要等待则返回null，如果Napcat不在线直接返回false
     */
    private function base(string $action, array $params = [], bool $wait = false): mixed
    {
        if (!$this->status()) return false;
        // 创建一个唯一的会话ID作为标识
        $echo = session_create_id();
        // 将操作、参数和标识编码为JSON并加入队列
        Queue::getInstance('napcat.messages')->enqueue(json_encode(['action' => $action, 'params' => $params, 'echo' => $wait ? $echo : ''], JSON_UNESCAPED_UNICODE));
        // 如果需要等待结果
        if ($wait) {
            // 最多尝试20次，每次间隔0.1秒
            for ($i = 0; $i < 20; $i++) {
                usleep(100000); // 暂停0.1秒
                // 从缓存中获取结果
                $data = Cache::get($echo);
                if ($data !== null) {
                    // 获取到结果后删除缓存并返回
                    Cache::delete($echo);
                    return $data;
                }
            }
        }
        // 不需要等待或超时后返回null
        return null;
    }

    /**
     * 设置消息已读
     * @param string|int $id 用户ID或群组ID
     * @param bool $is_group 是否为群组消息
     */
    public function mark_msg_as_read(string|int $id, bool $is_group = false): void
    {
        $this->base(__FUNCTION__, $is_group ? ['group_id' => $id] : ['user_id' => $id]);
    }

    /**
     * 标记私聊消息已读
     * @param string|int $id 用户ID
     */
    public function mark_private_msg_as_read(string|int $id): void
    {
        $this->base(__FUNCTION__, ['user_id' => $id]);
    }

    /**
     * 标记群组消息已读
     * @param string|int $id 群组ID
     */
    public function mark_group_msg_as_read(string|int $id): void
    {
        $this->base(__FUNCTION__, ['group_id' => $id]);
    }

    /**
     * 最近消息列表
     * @param int $count 获取消息数量，默认为10
     * @return array 返回消息列表数组
     */
    public function get_recent_contact(int $count = 10): array
    {
        return $this->base(__FUNCTION__, ['count' => $count], true)['data'] ?? [];
    }

    /**
     * 设置所有消息已读
     */
    public function _mark_all_as_read(): void
    {
        $this->base(__FUNCTION__);
    }

    /**
     * 点赞
     * @param string|int $user_id 用户ID
     * @param int $times 点赞次数，默认为1
     */
    public function send_like(string|int $user_id, int $times = 1): void
    {
        $this->base(__FUNCTION__, ['user_id' => $user_id, 'times' => $times]);
    }

    /**
     * 处理好友添加请求
     * @param string $flag 请求ID
     * @param bool $approve 是否同意添加
     * @param string $remark 好友备注
     */
    public function set_friend_add_request(string $flag, bool $approve, string $remark = ''): void
    {
        $this->base(__FUNCTION__, ['flag' => $flag, 'approve' => $approve, 'remark' => $remark]);
    }

    /**
     * 获取账号信息
     * @param string|int $user_id 用户ID
     * @return array 返回用户信息数组
     */
    public function get_stranger_info(string|int $user_id): array
    {
        return $this->base(__FUNCTION__, ['user_id' => $user_id], true)['data'] ?? [];
    }

    /**
     * 获取好友列表
     * @param bool $no_cache 是否不使用缓存
     * @return array 返回好友列表数组
     */
    public function get_friend_list(bool $no_cache = false): array
    {
        return $this->base(__FUNCTION__, ['no_cache' => $no_cache], true)['data'] ?? [];
    }

    /**
     * 获取好友分组列表
     * @return array 返回好友分组列表数组，失败返回空数组
     */
    public function get_friends_with_category(): array
    {
        return $this->base(__FUNCTION__, [], true)['data'] ?? [];
    }

    /**
     * 设置账号信息
     * @param string $nickname 昵称
     * @param string $personal_note 个性签名，默认为空字符串
     * @param string $sex 性别，默认为'unknown'，male为男性，female为女性
     * @param bool $result 是否返回结果，默认为false
     * @return array 返回设置后的个人资料数据，如果失败则返回空数组
     */
    public function set_qq_profile(string $nickname, string $personal_note = '', string $sex = 'unknown', bool $result = false): array
    {
        return $this->base(__FUNCTION__, ['nickname' => $nickname, 'personal_note' => $personal_note, 'sex' => $sex], $result)['data'] ?? [];
    }

    /**
     * 删除好友
     * @param bool $temp_block 拉黑
     * @param bool $temp_both_del 双向删除
     * @param string|int $user_id 用户ID，和friend_id二选一
     * @param string|int $friend_id 好友ID，和user_id二选一
     * @param bool $result 是否返回结果，默认为false
     * @return array 返回结果和错误信息数组，失败返回空数组
     */
    public function delete_friend(bool $temp_block, bool $temp_both_del, string|int $user_id = '', string|int $friend_id = '', bool $result = false): array
    {
        return $this->base(__FUNCTION__, ['temp_block' => $temp_block, 'temp_both_del' => $temp_both_del, 'user_id' => $user_id, 'friend_id' => $friend_id], $result)['data'] ?? [];
    }

    /**
     * 获取推荐好友/群聊卡片
     * @param string|int $group_id 群组ID，和user_id二选一
     * @param string|int $user_id 用户ID，和group_id二选一
     * @param string $phoneNumber 对方手机号，可选参数
     * @return array 返回包含arkJson的数组，失败返回空数组
     */
    public function ArkSharePeer(string|int $group_id = '', string|int $user_id = '', string $phoneNumber = ''): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'user_id' => $user_id, 'phoneNumber' => $phoneNumber], true)['data'] ?? [];
    }

    /**
     * 获取推荐群聊卡片
     * @param string $group_id QQ群号
     * @return string 返回分享的卡片json，错误返回空
     */
    public function ArkShareGroup(string|int $group_id): string
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id], true)['data'] ?? '';
    }

    /**
     * 设置在线状态，具体文档：https://napcat.apifox.cn/411631077e0
     * @param int $status 在线状态码，默认值为10
     * @param int $extStatus 扩展状态码，默认值为0
     * @param int $batteryStatus 电量，默认值为0
     */
    public function set_online_status(int $status = 10, int $extStatus = 0, int $batteryStatus = 0): void
    {
        $this->base(__FUNCTION__, ['status' => $status, 'extStatus' => $extStatus, 'batteryStatus' => $batteryStatus]);
    }

    /**
     * 设置自定义在线状态，具体文档：https://napcat.apifox.cn/411631078e0
     * @param string|int $face_id 表情ID
     * @param string|int $face_type 表情类型，默认值为0
     * @param string $wording 自定义内容的描述文本，默认值为'描述文本'
     */
    public function set_diy_online_status(string|int $face_id, string|int $face_type = 0, string $wording = '描述文本'): void
    {
        $this->base(__FUNCTION__, ['face_id' => $face_id, 'face_type' => $face_type, 'wording' => $wording]);
    }

    /**
     * 设置QQ头像
     * @param string $file 路径或链接
     */
    public function set_qq_avatar(string $file): void
    {
        $this->base(__FUNCTION__, ['file' => $file]);
    }

    /**
     * 创建收藏
     * @param string $rawData 内容
     * @param string $brief 标题
     */
    public function create_collection(string $rawData, string $brief): void
    {
        $this->base(__FUNCTION__, ['rawData' => $rawData, 'brief' => $brief]);
    }

    /**
     * 设置个性签名
     * @param string $longNick 内容
     * @param bool $result 是否返回结果，默认为false
     * @return array 返回结果和错误信息数组，失败返回空数组
     */
    public function set_self_longnick(string $longNick, bool $result = false): array
    {
        return $this->base(__FUNCTION__, ['longNick' => $longNick], $result)['data'] ?? [];
    }

    /**
     * 获取收藏表情
     * @param int $count 获取的收藏表情数量，默认为48张
     * @return array 返回获取到的收藏表情数据数组，如果获取失败则返回空数组
     */
    public function fetch_custom_face(int $count = 48): array
    {
        return $this->base(__FUNCTION__, ['count' => $count], true)['data'] ?? [];
    }

    /**
     * 获取点赞列表
     * @param string|int $user_id 指定用户，不填为获取所有
     * @param int $start 起始位置，默认为0
     * @param int $count 获取数量，默认为10
     * @return array 返回用户点赞列表数据，如果无数据则返回空数组
     */
    public function get_profile_like(string|int $user_id = '', int $start = 0, int $count = 10): array
    {
        return $this->base(__FUNCTION__, ['user_id' => $user_id, 'start' => $start, 'count' => $count], true)['data'] ?? [];
    }

    /**
     * 获取用户状态信息
     * @param string|int $user_id 用户ID
     * @return array 返回用户状态信息数组，如果获取失败则返回空数组
     */
    public function nc_get_user_status(string|int $user_id): array
    {
        return $this->base(__FUNCTION__, ['user_id' => $user_id], true)['data'] ?? [];
    }

    /**
     * 获取小程序卡片
     * @param string $type 卡片类型，枚举值: bili 哔哩哔哩、weibo 微博
     * @param string $title 卡片标题
     * @param string $desc 卡片描述
     * @param string $picUrl 卡片图片URL
     * @param string $jumpUrl 跳转URL
     * @param string $webUrl 可选，网页URL
     * @param bool $rawArkData 是否返回原始ark数据
     * @return array 返回小程序卡片数据数组，如果获取失败则返回空数组
     */
    public function get_mini_app_ark(string $type, string $title, string $desc, string $picUrl, string $jumpUrl, string $webUrl = '', bool $rawArkData = false): array
    {
        return $this->base(__FUNCTION__, [
            'type' => $type,
            'title' => $title,
            'desc' => $desc,
            'picUrl' => $picUrl,
            'jumpUrl' => $jumpUrl,
            'webUrl' => $webUrl,
            'rawArkData' => $rawArkData,
        ], true)['data'] ?? [];
    }

    /**
     * 获取单向好友列表
     * @return array 返回单向好友列表，如果获取失败则返回空数组
     */
    public function get_unidirectional_friend_list(): array
    {
        return $this->base(__FUNCTION__, [], true)['data'] ?? [];
    }

    /**
     * 获取登录号信息
     * @return array 返回登录号信息，如果获取失败则返回空数组
     */
    public function get_login_info(): array
    {
        return $this->base(__FUNCTION__, [], true)['data'] ?? [];
    }

    /**
     * 获取状态
     * @return array 返回状态信息，如果获取失败则返回空数组
     */
    public function get_status(): array
    {
        return $this->base(__FUNCTION__, [], true)['data'] ?? [];
    }

    /**
     * 获取当前账号在线客户端列表
     * @return array 返回客户端列表，如果获取失败则返回空数组
     */
    public function get_online_clients(): array
    {
        return $this->base(__FUNCTION__, [], true)['data'] ?? [];
    }

    /**
     * 获取在线机型
     * @param string $model 机型
     * @return array 返回在线机型，如果获取失败则返回空数组
     */
    public function _get_model_show(string $model = 'napcat'): array
    {
        return $this->base(__FUNCTION__, ['model' => $model], true)['data'] ?? [];
    }

    /**
     * 设置在线机型
     */
    public function _set_model_show(): void
    {
        $this->base(__FUNCTION__);
    }

    /**
     * 获取被过滤好友请求
     * @param int $count 获取的数量
     * @return array 返回好友请求数组，如果获取失败则返回空数组
     */
    public function get_doubt_friends_add_request(int $count = 50): array
    {
        return $this->base(__FUNCTION__, ['count' => $count], true)['data'] ?? [];
    }

    /**
     * 处理被过滤好友请求，
     * 在 4.7.43 版本中，
     * approve 的值无效，
     * 调用该接口既是同意好友请求！！！
     * @param string $flag 好友添加请求的标识符
     * @param bool $approve 是否批准添加请求，默认为true
     */
    public function set_doubt_friends_add_request(string $flag, bool $approve = true): void
    {
        $this->base(__FUNCTION__, ['flag' => $flag, 'approve' => $approve]);
    }

    /**
     * 设置好友备注
     * @param string|int $user_id 好友的用户ID
     * @param string $remark 备注名
     */
    public function set_friend_remark(string|int $user_id, string $remark): void
    {
        $this->base(__FUNCTION__, ['user_id' => $user_id, 'remark' => $remark]);
    }

    /**
     * 发送群聊消息
     * @param string|int $group_id 群组ID
     * @param array $message 消息内容数组，具体格式参考：https://napcat.apifox.cn/77363184f0
     * @param bool $result 是否返回结果，默认为false
     * @return string 返回消息ID，如果发送失败则返回空
     */
    public function send_group_msg(string|int $group_id, array $message, bool $result = false): string
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'message' => $message], $result)['data']['message_id'] ?? '';
    }

    /**
     * 发送群合并转发消息
     * @param string|int $group_id 群组ID
     * @param array $message 消息内容数组，具体格式参考：https://napcat.apifox.cn/411631118e0
     * @param bool $result 是否返回结果，默认为false
     * @return array 返回message_id与res_id数组，失败则返回空数组
     */
    public function send_group_forward_msg(string|int $group_id, array $message, bool $result = false): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'message' => $message], $result)['data'] ?? [];
    }

    /**
     * 消息转发到群
     * @param string|int $group_id 群组ID
     * @param string|int $message_id 消息ID
     */
    public function forward_group_single_msg(string|int $group_id, string|int $message_id): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'message_id' => $message_id]);
    }

    /**
     * 发送群聊戳一戳
     * @param string|int $group_id 群组ID
     * @param string|int $user_id 用户ID
     */
    public function group_poke(string|int $group_id, string|int $user_id): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'user_id' => $user_id]);
    }

    /**
     * 发送私聊消息
     * @param string|int $user_id 用户ID
     * @param array $message 消息内容数组，具体格式参考：https://napcat.apifox.cn/77363185f0
     * @param bool $result 是否返回结果，默认为false
     * @return string 返回消息ID，如果发送失败则返回空
     */
    public function send_private_msg(string|int $user_id, array $message, bool $result = false): string
    {
        return $this->base(__FUNCTION__, ['user_id' => $user_id, 'message' => $message], $result)['data']['message_id'] ?? '';
    }

    /**
     * 发送私聊合并转发消息
     * @param string|int $user_id 用户ID
     * @param array $message 消息内容数组，具体格式参考：https://napcat.apifox.cn/411631132e0
     * @param bool $result 是否返回结果，默认为false
     * @return array 返回message_id与res_id数组，失败则返回空数组
     */
    public function send_private_forward_msg(string|int $user_id, array $message, bool $result = false): array
    {
        return $this->base(__FUNCTION__, ['user_id' => $user_id, 'message' => $message], $result)['data'] ?? [];
    }

    /**
     * 消息转发到私聊
     * @param string|int $user_id 用户ID
     * @param string|int $message_id 消息ID
     */
    public function forward_friend_single_msg(string|int $user_id, string|int $message_id): void
    {
        $this->base(__FUNCTION__, ['user_id' => $user_id, 'message_id' => $message_id]);
    }

    /**
     * 发送私聊戳一戳
     * @param string|int $user_id 私聊对象
     * @param string|int $target_id 戳一戳对象，可不填
     */
    public function friend_poke(string|int $user_id, string|int $target_id = ''): void
    {
        $this->base(__FUNCTION__, ['user_id' => $user_id, 'target_id' => $target_id]);
    }

    /**
     * 发送戳一戳
     * @param string|int $user_id 私聊对象
     * @param string|int $group_id 不填则为私聊戳
     * @param string|int $target_id 戳一戳对象，可不填
     */
    public function send_poke(string|int $user_id, string|int $group_id = '', string|int $target_id = ''): void
    {
        $this->base(__FUNCTION__, ['user_id' => $user_id, 'group_id' => $group_id, 'target_id' => $target_id]);
    }

    /**
     * 撤回消息
     * @param string|int $message_id 要删除的消息ID
     */
    public function delete_msg(string|int $message_id): void
    {
        $this->base(__FUNCTION__, ['message_id' => $message_id]);
    }

    /**
     * 获取群历史消息
     * @param string|int $group_id 群组ID
     * @param int $message_seq 0为最新
     * @param int $count 获取消息数量，默认为20
     * @param bool $reverseOrder 是否按倒序排列，默认为false
     * @return array 返回消息历史记录数组，如果没有数据则返回空数组，格式参考：https://napcat.apifox.cn/411631097e0
     */
    public function get_group_msg_history(string|int $group_id, int $message_seq = 0, int $count = 20, bool $reverseOrder = false): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'message_seq' => $message_seq, 'count' => $count, 'reverseOrder' => $reverseOrder], true)['data'] ?? [];
    }

    /**
     * 获取消息详情
     * @param string|int $message_id 消息ID，可以是字符串或整数类型
     * @return array 返回消息详情，如果不存在则返回空数组
     */
    public function get_msg(string|int $message_id): array
    {
        return $this->base(__FUNCTION__, ['message_id' => $message_id], true)['data'] ?? [];
    }

    /**
     * 获取合并转发消息
     * @param string|int $message_id 消息ID，可以是字符串类型或整数类型
     * @return array 返回包含转发消息信息的数组
     */
    public function get_forward_msg(string|int $message_id): array
    {
        return $this->base(__FUNCTION__, ['message_id' => $message_id], true)['data']['messages'] ?? [];
    }

    /**
     * 贴表情
     * @param string|int $message_id 消息ID，可以是字符串或整数类型
     * @param int $emoji_id 表情ID，必须是整数类型
     * @param bool $set 是否贴
     * @param bool $result 是否返回结果，默认为false
     * @return array 返回操作结果的数据数组，如果无数据则返回空数组
     */
    public function set_msg_emoji_like(string|int $message_id, int $emoji_id, bool $set, bool $result = false): array
    {
        return $this->base(__FUNCTION__, ['message_id' => $message_id, 'emoji_id' => $emoji_id, 'set' => $set], $result)['data'] ?? [];
    }

    /**
     * 获取好友消息历史记录
     * @param string|int $user_id 好户ID，可以是字符串或整数类型
     * @param string|int $message_seq 消息序列号，0为最新，可以是字符串或整数类型
     * @param int $count 获取消息数量，默认为20
     * @param bool $reverseOrder 是否倒序返回消息，默认为false
     * @return array 返回消息历史记录数组，如果没有数据则返回空数组
     */
    public function get_friend_msg_history(string|int $user_id, string|int $message_seq = '', int $count = 20, bool $reverseOrder = false): array
    {
        return $this->base(__FUNCTION__, ['user_id' => $user_id, 'message_seq' => $message_seq, 'count' => $count, 'reverseOrder' => $reverseOrder], true)['data']['messages'] ?? [];
    }

    /**
     * 获取贴表情详情
     * @param string|int $message_id 消息ID，可以是字符串或整数类型
     * @param string $emojiId 表情ID
     * @param string $emojiType 表情类型
     * @param int $count 获取数量，默认为20
     * @return array 返回表情点赞列表数组
     */
    public function fetch_emoji_like(string|int $message_id, string $emojiId, string $emojiType, int $count = 20): array
    {
        return $this->base(__FUNCTION__, ['message_id' => $message_id, 'emojiId' => $emojiId, 'emojiType' => $emojiType, 'count' => $count], true)['data'] ?? [];
    }

    /**
     * 发送合并转发消息
     * @param array $messages 消息内容数组，包含要转发的消息，参考：https://napcat.apifox.cn/411631103e0
     * @param string|int $group_id 群组ID，可选参数，默认为0
     * @param string|int $user_id 用户ID，可选参数，默认为0
     * @param bool $result 是否返回结果，可选参数，默认为false
     * @return array 返回处理后的消息数组
     */
    public function send_forward_msg(array $messages, string|int $group_id = 0, string|int $user_id = 0, bool $result = false): array
    {
        return $this->base(__FUNCTION__, ['messages' => $messages, 'group_id' => $group_id, 'user_id' => $user_id], $result)['data'] ?? [];
    }

    /**
     * 获取语音消息详情
     * @param string $file 录音文件路径（可选）
     * @param string $file_id 录音文件ID（可选）
     * @param string $out_format 输出格式，可选值:mp3 amr wma m4a spx ogg wav flac，默认为'mp3'
     * @return array 返回录音文件相关信息
     */
    public function get_record(string $file = '', string $file_id = '', string $out_format = 'mp3'): array
    {
        return $this->base(__FUNCTION__, ['file' => $file, 'file_id' => $file_id, 'out_format' => $out_format], true)['data'] ?? [];
    }

    /**
     * 获取图片消息详情
     * @param string $file 录音文件路径，和file_id二选一
     * @param string $file_id 录音文件ID，和file二选一
     * @return array 返回图片相关信息
     */
    public function get_image(string $file = '', string $file_id = ''): array
    {
        return $this->base(__FUNCTION__, ['file' => $file, 'file_id' => $file_id], true)['data'] ?? [];
    }

    /**
     * 发送群AI语音
     * @param string|int $group_id 群组ID，可以是字符串或整数类型
     * @param string $character AI角色名称，character_id
     * @param string $text 要发送的文本内容
     * @param bool $result 是否返回结果，可选参数，默认为false
     * @return string 返回message_id，失败返回空
     */
    public function send_group_ai_record(string|int $group_id, string $character, string $text, bool $result = false): string
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'character' => $character, 'text' => $text], $result)['data']['message_id'] ?? '';
    }

    /**
     * 设置群加群选项
     * @param string|int $group_id 群组ID
     * @param string $add_type 加群类型，可选值：0（允许所有人）、1（需要验证）、2（需要回答问题）、3（禁止加群）
     * @param string $group_question 加群问题，当add_type为2时必填
     * @param string $group_answer 加群答案，当add_type为2时必填
     */
    public function set_group_add_option(string|int $group_id, string $add_type, string $group_question = '', string $group_answer = ''): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'add_type' => $add_type, 'group_question' => $group_question, 'group_answer' => $group_answer]);
    }

    /**
     * 设置群机器人加群选项
     * @param string|int $group_id 群组ID
     * @param int $robot_member_switch 机器人成员开关，0为关闭，1为开启
     * @param int $robot_member_examine 机器人成员审核，0为关闭，1为开启
     */
    public function set_group_robot_add_option(string|int $group_id, int $robot_member_switch = 0, int $robot_member_examine = 0): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'robot_member_switch' => $robot_member_switch, 'robot_member_examine' => $robot_member_examine]);
    }

    /**
     * 批量踢出群成员
     * @param string|int $group_id 群组ID
     * @param array $user_id 用户ID数组
     * @param bool $reject_add_request 是否群拉黑，默认为true
     * @return void
     */
    public function set_group_kick_members(string|int $group_id, array $user_id, bool $reject_add_request = true): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'user_id' => $user_id, 'reject_add_request' => $reject_add_request]);
    }

    /**
     * 设置群备注
     * @param string|int $group_id 群组ID
     * @param string $remark 群备注
     * @return void
     */
    public function set_group_remark(string|int $group_id, string $remark): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'remark' => $remark]);
    }

    /**
     * 设置群头衔
     * @param string|int $group_id 群组ID
     * @param string|int $user_id 用户ID
     * @param string $special_title 头衔内容
     * @return void
     */
    public function set_group_special_title(string|int $group_id, string|int $user_id, string $special_title = ''): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'user_id' => $user_id, 'special_title' => $special_title]);
    }

    /**
     * 获取群详细信息
     * @param string|int $group_id 群组ID
     * @return array 返回群详情
     */
    public function get_group_detail_info(string|int $group_id): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id], true)['data'] ?? [];
    }

    /**
     * 群踢人
     * @param string|int $group_id 群组ID
     * @param string|int $user_id 用户ID
     * @param bool $reject_add_request 是否群拉黑，默认为false
     */
    public function set_group_kick(string|int $group_id, string|int $user_id, bool $reject_add_request = false): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'user_id' => $user_id, 'reject_add_request' => $reject_add_request]);
    }

    /**
     * 获取群系统消息
     * @param int $count 获取消息数量，默认为50
     * @return array 返回群系统消息列表，如果获取失败则返回空数组
     */
    public function get_group_system_msg(int $count = 50): array
    {
        return $this->base(__FUNCTION__, ['count' => $count], true)['data'] ?? [];
    }

    /**
     * 群禁言
     * @param string|int $group_id 群组ID
     * @param string|int $user_id 用户ID
     * @param int $duration 禁言时长（秒）
     */
    public function set_group_ban(string|int $group_id, string|int $user_id, int $duration): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'user_id' => $user_id, 'duration' => $duration]);
    }

    /**
     * 获取群精华消息列表
     * @param string|int $group_id 群组ID
     * @return array 返回群精华消息列表，如果获取失败则返回空数组
     */
    public function get_essence_msg_list(string|int $group_id): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id], true)['data'] ?? [];
    }

    /**
     * 群禁言所有成员
     * @param string|int $group_id 群组ID
     * @param bool $enable 是否禁言所有成员，默认为true
     */
    public function set_group_whole_ban(string|int $group_id, bool $enable = true): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'enable' => $enable]);
    }

    /**
     * 设置群头像
     * @param string|int $group_id 群组ID
     * @param string $file 群头像文件路径
     * @return void
     */
    public function set_group_portrait(string|int $group_id, string $file): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'file' => $file]);
    }

    /**
     * 设置群管理员
     * @param string|int $group_id 群组ID
     * @param string|int $user_id 用户ID
     * @param bool $enable 是否设置为管理员，默认为true
     * @return void
     */
    public function set_group_admin(string|int $group_id, string|int $user_id, bool $enable = true): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'user_id' => $user_id, 'enable' => $enable]);
    }

    /**
     * 设置群名片
     * @param string|int $group_id 群组ID
     * @param string|int $user_id 用户ID
     * @param string $card 群名片
     * @return void
     */
    public function set_group_card(string|int $group_id, string|int $user_id, string $card): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'user_id' => $user_id, 'card' => $card]);
    }

    /**
     * 设置群精华消息
     * @param string|int $message_id 群精华消息ID
     * @return array
     */
    public function set_essence_msg(string|int $message_id): array
    {
        return $this->base(__FUNCTION__, ['message_id' => $message_id], true)['data'] ?? [];
    }

    /**
     * 设置群名称
     * @param string|int $group_id 群组ID
     * @param string $group_name 群名称
     * @return void
     */
    public function set_group_name(string|int $group_id, string $group_name): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'group_name' => $group_name]);
    }

    /**
     * 删除群精华消息
     * @param string|int $message_id 群精华消息ID
     * @return array
     */
    public function delete_essence_msg(string|int $message_id): array
    {
        return $this->base(__FUNCTION__, ['message_id' => $message_id], true)['data'] ?? [];
    }

    /**
     * 删除群公告
     * @param string|int $group_id 群组ID
     * @param string $notice_id 公告ID
     * @return void
     */
    public function _del_group_notice(string|int $group_id, string $notice_id): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'notice_id' => $notice_id]);
    }

    /**
     * 退群
     * @param string|int $group_id 群组ID
     * @param bool $is_dismiss 是否退出群，默认为true
     * @return void
     */
    public function set_group_leave(string|int $group_id, bool $is_dismiss = true): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'is_dismiss' => $is_dismiss]);
    }

    /**
     * 发送群公告
     * @param string|int $group_id 群组ID
     * @param string $content 公告内容
     * @param string $image 图片URL，默认为空字符串
     * @param string|int $pinned 是否置顶，默认为0
     * @param string|int $type 公告类型，默认为0
     * @param string|int $confirm_required 是否需要确认，默认为0
     * @param string|int $is_show_edit_card 是否显示编辑卡片，默认为0
     * @param string|int $tip_window_type 提示窗口类型，默认为0
     * @return void
     */
    public function _send_group_notice(string|int $group_id, string $content, string $image = '', string|int $pinned = 0, string|int $type = 0, string|int $confirm_required = 0, string|int $is_show_edit_card = 0, string|int $tip_window_type = 0): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'content' => $content, 'image' => $image, 'pinned' => $pinned, 'type' => $type, 'confirm_required' => $confirm_required, 'is_show_edit_card' => $is_show_edit_card, 'tip_window_type' => $tip_window_type]);
    }

    /**
     * 设置群搜索
     * @param string|int $group_id 群组ID
     * @param int $no_code_finger_open 是否开启无码指纹搜索，默认为0
     * @param int $no_finger_open 是否开启指纹搜索，默认为0
     * @return void
     */
    public function set_group_search(string|int $group_id, int $no_code_finger_open = 0, int $no_finger_open = 0): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'no_code_finger_open' => $no_code_finger_open, 'no_finger_open' => $no_finger_open]);
    }

    /**
     * 获取群公告
     * @param string|int $group_id 群组ID
     * @return array
     */
    public function _get_group_notice(string|int $group_id): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id], true)['data'] ?? [];
    }

    /**
     * 设置群加入请求
     * @param string $flag 加入请求id
     * @param bool $approve 是否同意加入
     * @param string $reason 拒绝原因，默认为空字符串
     * @return void
     */
    public function set_group_add_request(string $flag, bool $approve, string $reason = ''): void
    {
        $this->base(__FUNCTION__, ['flag' => $flag, 'approve' => $approve, 'reason' => $reason]);
    }

    /**
     * 获取群信息
     * @param string|int $group_id 群组ID
     * @return array
     */
    public function get_group_info(string|int $group_id): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id], true)['data'] ?? [];
    }

    /**
     * 获取群列表
     * @param bool $no_cache 是否不缓存，默认为false
     * @return array
     */
    public function get_group_list(bool $no_cache = false): array
    {
        return $this->base(__FUNCTION__, ['no_cache' => $no_cache], true)['data'] ?? [];
    }

    /**
     * 获取群成员信息
     * @param string|int $group_id 群组ID
     * @param string|int $user_id 用户ID
     * @param bool $no_cache 是否不缓存，默认为false
     * @return array
     */
    public function get_group_member_info(string|int $group_id, string|int $user_id, bool $no_cache = false): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'user_id' => $user_id, 'no_cache' => $no_cache], true)['data'] ?? [];
    }

    /**
     * 获取群成员列表
     * @param string|int $group_id 群组ID
     * @param bool $no_cache 是否不缓存，默认为false
     * @return array
     */
    public function get_group_member_list(string|int $group_id, bool $no_cache = false): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'no_cache' => $no_cache], true)['data'] ?? [];
    }

    /**
     * 获取群荣誉信息
     * @param string|int $group_id 群组ID
     * @param string $type 看类型，默认为all
     * @return array
     */
    public function get_group_honor_info(string|int $group_id, string $type = 'all'): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'type' => $type], true)['data'] ?? [];
    }

    /**
     * 获取群信息扩展
     * @param string|int $group_id 群组ID
     * @return array
     */
    public function get_group_info_ex(string|int $group_id): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id], true)['data'] ?? [];
    }

    /**
     * 获取群@所有成员剩余次数
     * @param string|int $group_id 群组ID
     * @return array
     */
    public function get_group_at_all_remain(string|int $group_id): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id], true)['data'] ?? [];
    }

    /**
     * 获取群禁言列表
     * @param string|int $group_id 群组ID
     * @return array
     */
    public function get_group_shut_list(string|int $group_id): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id], true)['data'] ?? [];
    }

    /**
     * 获取群过滤系统消息
     * @return array
     */
    public function get_group_ignored_notifies(): array
    {
        return $this->base(__FUNCTION__, [], true)['data'] ?? [];
    }

    /**
     * 群打卡
     * @param string|int $group_id 群组ID
     * @return void
     */
    public function set_group_sign(string|int $group_id): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id]);
    }

    /**
     * 群打卡
     * @param string|int $group_id 群组ID
     * @return void
     */
    public function send_group_sign(string|int $group_id): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id]);
    }

    /**
     * 设置群待办事项
     * @param string $group_id 群组ID
     * @param string $message_id 消息ID
     * @param string $message_seq 消息序列号，默认为空字符串
     * @return void
     */
    public function set_group_todo(string $group_id, string $message_id, string $message_seq = ''): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'message_id' => $message_id, 'message_seq' => $message_seq]);
    }

    /**
     * 获取cookies
     * @param string $domain 域名
     * @return array
     */
    public function get_cookies(string $domain): array
    {
        return $this->base(__FUNCTION__, ['domain' => $domain], true)['data'] ?? [];
    }

    /**
     * 获取CSRF token
     * @return int
     */
    public function get_csrf_token(): int
    {
        return $this->base(__FUNCTION__, [], true)['data']['token'] ?? 0;
    }

    /**
     * 获取凭证
     * @param string $domain 域名
     * @return array
     */
    public function get_credentials(string $domain): array
    {
        return $this->base(__FUNCTION__, ['domain' => $domain], true)['data'] ?? [];
    }

    /**
     * nc获取rkey
     * @return array
     */
    public function nc_get_rkey(): array
    {
        return $this->base(__FUNCTION__, [], true)['data'] ?? [];
    }

    /**
     * 获取rkey
     * @return array
     */
    public function get_rkey(): array
    {
        return $this->base(__FUNCTION__, [], true)['data'] ?? [];
    }

    /**
     * 获取clientkey
     * @return string
     */
    public function get_clientkey(): string
    {
        return $this->base(__FUNCTION__, [], true)['data']['clientkey'] ?? '';
    }

    /**
     * 获取rkey_server
     * @return array
     */
    public function get_rkey_server(): array
    {
        return $this->base(__FUNCTION__, [], true)['data'] ?? [];
    }

    /**
     * 是否可以发送图片
     * @return bool
     */
    public function can_send_image(): bool
    {
        return $this->base(__FUNCTION__, [], true)['data']['yes'] ?? false;
    }

    /**
     * 是否可以发送语音
     * @return bool
     */
    public function can_send_record(): bool
    {
        return $this->base(__FUNCTION__, [], true)['data']['yes'] ?? false;
    }

    /**
     * 图片OCR，仅 Windows 可用
     * @param string $image 图片
     * @return array
     */
    public function ocr_image(string $image): array
    {
        return $this->base(__FUNCTION__, ['image' => $image], true)['data'] ?? [];
    }

    /**
     * 英文翻译为中文
     * @param array $words 英文数组
     * @return array
     */
    public function translate_en2zh(array $words): array
    {
        return $this->base(__FUNCTION__, ['words' => $words], true)['data'] ?? [];
    }

    /**
     * 设置输入状态
     * @param string|int $user_id 用户ID
     * @param int $event_type 事件类型
     * @return void
     */
    public function set_input_status(string|int $user_id, int $event_type): void
    {
        $this->base(__FUNCTION__, ['user_id' => $user_id, 'event_type' => $event_type]);
    }

    /**
     * 获取AI语音人物
     * @param string|int $group_id 群组ID
     * @param string|int $chat_type 语音类型，默认1;  1 or 2?
     * @return array
     */
    public function get_ai_characters(string|int $group_id, string|int $chat_type = 1): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'chat_type' => $chat_type], true)['data'] ?? [];
    }

    /**
     * 获取AI语音
     * @param string|int $group_id 群组ID
     * @param string $character 人物ID, character_id
     * @param string $text 文本内容
     * @return string 语音URL
     */
    public function get_ai_record(string|int $group_id, string $character, string $text): string
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'character' => $character, 'text' => $text], true)['data'] ?? '';
    }

    /**
     * 点击按钮
     * @param string|int $group_id 群组ID
     * @param string $bot_appid 机器人应用ID, 从机器人应用列表中获取
     * @param string $button_id 按钮ID
     * @param string $callback_data 回调数据
     * @param string $msg_seq 消息序列号
     * @return array
     */
    public function click_inline_keyboard_button(string|int $group_id, string $bot_appid, string $button_id, string $callback_data, string $msg_seq): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'bot_appid' => $bot_appid, 'button_id' => $button_id, 'callback_data' => $callback_data, 'msg_seq' => $msg_seq], true)['data'] ?? [];
    }

    /**
     * 获取版本信息
     * @return array
     */
    public function get_version_info(): array
    {
        return $this->base(__FUNCTION__, [], true)['data'] ?? [];
    }

    /**
     * 获取packet状态
     * @return array
     */
    public function nc_get_packet_status(): array
    {
        return $this->base(__FUNCTION__, [], true) ?? [];
    }

    /**
     * 获取机器人uin范围
     * @return array
     */
    public function get_robot_uin_range(): array
    {
        return $this->base(__FUNCTION__, [], true)['data'] ?? [];
    }

    /**
     * 退出机器人
     * @return void
     */
    public function bot_exit(): void
    {
        $this->base(__FUNCTION__);
    }

    /**
     * 清理流临时文件
     * @return void
     */
    public function clean_stream_temp_file(): void
    {
        $this->base(__FUNCTION__);
    }

    /**
     * 流式下载测试
     * @param bool $error 是否模拟错误
     * @return array
     */
    public function test_download_stream(bool $error = false): array
    {
        return $this->base(__FUNCTION__, ['error' => $error], true)['data'] ?? [];
    }

    /**
     * 流式上传文件
     * @param string $stream_id 流ID
     * @param string $chunk_data 分块数据
     * @param int $chunk_index 分块索引
     * @param int $total_chunks 总分块数
     * @param int $file_size 文件大小
     * @param string $expected_sha256 预期的SHA256值
     * @param bool $is_complete 是否完成上传
     * @param string $file_name 文件名
     * @param bool $reset 是否重置
     * @param bool $verify_only 是否仅验证文件
     * @param int $file_retention 文件保留时间，默认300000
     * @return array
     */
    public function upload_file_stream(string $stream_id, string $chunk_data = '', int $chunk_index = 0, int $total_chunks = 0, int $file_size = 0, string $expected_sha256 = '', bool $is_complete = true, string $file_name = '', bool $reset = true, bool $verify_only = true, int $file_retention = 300000): array
    {
        return $this->base(__FUNCTION__, ['stream_id' => $stream_id, 'chunk_data' => $chunk_data, 'chunk_index' => $chunk_index, 'total_chunks' => $total_chunks, 'file_size' => $file_size, 'expected_sha256' => $expected_sha256, 'is_complete' => $is_complete, 'file_name' => $file_name, 'reset' => $reset, 'verify_only' => $verify_only, 'file_retention' => $file_retention], true)['data'] ?? [];
    }

    /**
     * 流式下载文件
     * @param string $file 文件名
     * @param string $file_id 文件ID
     * @param int $chunk_size 分块大小，默认65536
     * @return array
     */
    public function download_file_stream(string $file = '', string $file_id = '', int $chunk_size = 65536): array
    {
        return $this->base(__FUNCTION__, ['file' => $file, 'file_id' => $file_id, 'chunk_size' => $chunk_size], true)['data'] ?? [];
    }

    /**
     * 流式下载语音文件
     * @param string $file 文件名
     * @param string $file_id 文件ID
     * @param int $chunk_size 分块大小，默认65536
     * @param string $out_format 输出格式
     * @return array
     */
    public function download_file_record_stream(string $file = '', string $file_id = '', int $chunk_size = 65536, string $out_format = ''): array
    {
        return $this->base(__FUNCTION__, ['file' => $file, 'file_id' => $file_id, 'chunk_size' => $chunk_size, 'out_format' => $out_format], true)['data'] ?? [];
    }

    /**
     * 流式下载图片文件
     * @param string $file 文件名
     * @param string $file_id 文件ID
     * @param int $chunk_size 分块大小，默认65536
     * @return array
     */
    public function download_file_image_stream(string $file = '', string $file_id = '', int $chunk_size = 65536): array
    {
        return $this->base(__FUNCTION__, ['file' => $file, 'file_id' => $file_id, 'chunk_size' => $chunk_size], true)['data'] ?? [];
    }

    /**
     * 上传群文件
     * @param string|int $group_id 群ID
     * @param string $file 文件路径
     * @param string $name 文件名
     * @param string $folder 文件夹，文件夹ID（二选一）
     * @param string $folder_id 文件夹，文件夹ID（二选一）
     * @return void
     */
    public function upload_group_file(string|int $group_id, string $file, string $name, string $folder = '', string $folder_id = ''): void
    {
        if ($folder_id === '') {
            $this->base(__FUNCTION__, ['group_id' => $group_id, 'file' => $file, 'name' => $name, 'folder' => $folder]);
        } else {
            $this->base(__FUNCTION__, ['group_id' => $group_id, 'file' => $file, 'name' => $name, 'folder_id' => $folder_id]);
        }
    }

    /**
     * 上传私聊文件
     * @param string|int $user_id 用户ID
     * @param string $file 文件路径
     * @param string $name 文件名
     * @return void
     */
    public function upload_private_file(string|int $user_id, string $file, string $name): void
    {
        $this->base(__FUNCTION__, ['user_id' => $user_id, 'file' => $file, 'name' => $name]);
    }

    /**
     * 获取群根目录文件
     * @param string|int $group_id 群ID
     * @param int $file_count 文件数量，默认50
     * @return array
     */
    public function get_group_root_files(string|int $group_id, int $file_count = 50): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'file_count' => $file_count], true)['data'] ?? [];
    }

    /**
     * 获取群文件夹文件
     * @param string|int $group_id 群ID
     * @param string $folder_id 文件夹ID（二选一）
     * @param string $folder 文件夹，文件夹ID（二选一）
     * @param int $file_count 文件数量，默认50
     * @return array
     */
    public function get_group_files_by_folder(string|int $group_id, string $folder_id = '', string $folder = '', int $file_count = 50): array
    {
        if ($folder_id === '') {
            return $this->base(__FUNCTION__, ['group_id' => $group_id, 'folder' => $folder, 'file_count' => $file_count], true)['data'] ?? [];
        } else {
            return $this->base(__FUNCTION__, ['group_id' => $group_id, 'folder_id' => $folder_id, 'file_count' => $file_count], true)['data'] ?? [];
        }
    }

    /**
     * 获取群文件系统信息
     * @param string|int $group_id 群ID
     * @return array
     */
    public function get_group_file_system_info(string|int $group_id): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id], true)['data'] ?? [];
    }

    /**
     * 获取文件信息
     * @param string $file_id 文件ID，二选一
     * @param string $file 文件名，二选一
     * @return array
     */
    public function get_file_info(string $file_id = '', string $file = ''): array
    {
        if ($file_id === '') {
            return $this->base(__FUNCTION__, ['file' => $file], true)['data'] ?? [];
        } else {
            return $this->base(__FUNCTION__, ['file_id' => $file_id], true)['data'] ?? [];
        }
    }

    /**
     * 获取群文件URL
     * @param string|int $group_id 群ID
     * @param string $file_id 文件ID
     * @return string
     */
    public function get_group_file_url(string|int $group_id, string $file_id): string
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'file_id' => $file_id], true)['data']['url'] ?? '';
    }

    /**
     * 获取私聊文件URL
     * @param string $file_id 文件ID
     * @return string
     */
    public function get_private_file_url(string $file_id): string
    {
        return $this->base(__FUNCTION__, ['file_id' => $file_id], true)['data']['url'] ?? '';
    }

    /**
     * 创建群文件夹
     * @param string|int $group_id 群ID
     * @param string $folder_name 文件夹名
     * @return array
     */
    public function create_group_file_folder(string|int $group_id, string $folder_name): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'folder_name' => $folder_name], true)['data'] ?? [];
    }

    /**
     * 删除群文件夹
     * @param string|int $group_id 群ID
     * @param string $file_id 文件ID
     * @return array
     */
    public function delete_group_file(string|int $group_id, string $file_id): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'file_id' => $file_id], true)['data'] ?? [];
    }

    /**
     * 删除群文件夹
     * @param string|int $group_id 群ID
     * @param string $folder_id 文件夹ID
     * @return array
     */
    public function delete_group_folder(string|int $group_id, string $folder_id): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'folder_id' => $folder_id], true)['data'] ?? [];
    }

    /**
     * 移动群文件
     * @param string|int $group_id 群ID
     * @param string $file_id 文件ID
     * @param string $current_parent_directory 当前父目录路径，根目录填 /
     * @param string $target_parent_directory 目标父目录路径
     * @return bool
     */
    public function move_group_file(string|int $group_id, string $file_id, string $current_parent_directory, string $target_parent_directory): bool
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'file_id' => $file_id, 'current_parent_directory' => $current_parent_directory, 'target_parent_directory' => $target_parent_directory], true)['data']['ok'] ?? false;
    }

    /**
     * 重命名群文件
     * @param string|int $group_id 群ID
     * @param string $file_id 文件ID
     * @param string $current_parent_directory 当前父目录路径，根目录填 /
     * @param string $new_name 新文件名
     * @return bool
     */
    public function rename_group_file(string|int $group_id, string $file_id, string $current_parent_directory, string $new_name): bool
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'file_id' => $file_id, 'current_parent_directory' => $current_parent_directory, 'new_name' => $new_name], true)['data']['ok'] ?? false;
    }

    /**
     * 转移群文件
     * @param string|int $group_id 群ID
     * @param string $file_id 文件ID
     * @return bool
     */
    public function trans_group_file(string|int $group_id, string $file_id): bool
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'file_id' => $file_id], true)['data']['ok'] ?? false;
    }

    /**
     * 下载文件
     * @param string $url 下载地址
     * @param string $base64 base64编码文件，和url二选一
     * @param string $name 自定义文件名称
     * @param array $headers 请求头数组
     * @return string
     */
    public function download_file(string $url = '', string $base64 = '', string $name = '', array $headers = []): string
    {
        return $this->base(__FUNCTION__, ['url' => $url, 'base64' => $base64, 'name' => $name, 'headers' => $headers], true)['data']['file'] ?? '';
    }

    /**
     * 删除群相册文件
     * @param string $group_id 群ID
     * @param string $album_id 相册ID
     * @param string $lloc 文件位置
     * @return void
     */
    public function del_group_album_media(string $group_id, string $album_id, string $lloc): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'album_id' => $album_id, 'lloc' => $lloc]);
    }

    /**
     * 设置群相册文件点赞
     * @param string $group_id 群ID
     * @param string $album_id 相册ID
     * @param string $lloc 文件位置
     * @param string $id 文件ID
     * @param bool $set 是否点赞，默认点赞
     * @return void
     */
    public function set_group_album_media_like(string $group_id, string $album_id, string $lloc, string $id, bool $set = true): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'album_id' => $album_id, 'lloc' => $lloc, 'id' => $id, 'set' => $set]);
    }

    /**
     * 群相册文件评论
     * @param string $group_id 群ID
     * @param string $album_id 相册ID
     * @param string $lloc 文件位置
     * @param string $content 评论内容
     * @return array
     */
    public function do_group_album_comment(string $group_id, string $album_id, string $lloc, string $content): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'album_id' => $album_id, 'lloc' => $lloc, 'content' => $content], true)['data'] ?? [];
    }

    /**
     * 获取群相册文件列表
     * @param string $group_id 群ID
     * @param string $album_id 相册ID
     * @param string $attach_info 附加信息
     * @return array
     */
    public function get_group_album_media_list(string $group_id, string $album_id, string $attach_info): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id, 'album_id' => $album_id, 'attach_info' => $attach_info], true)['data'] ?? [];
    }

    /**
     * 上传图片到群相册
     * @param string $group_id 群ID
     * @param string $album_id 相册ID
     * @param string $album_name 相册名称
     * @param string $file 文件路径
     * @return void
     */
    public function upload_image_to_qun_album(string $group_id, string $album_id, string $album_name, string $file): void
    {
        $this->base(__FUNCTION__, ['group_id' => $group_id, 'album_id' => $album_id, 'album_name' => $album_name, 'file' => $file]);
    }

    /**
     * 获取群相册列表
     * @param string $group_id 群ID
     * @return array
     */
    public function get_qun_album_list(string $group_id): array
    {
        return $this->base(__FUNCTION__, ['group_id' => $group_id], true)['data'] ?? [];
    }
}