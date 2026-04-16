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
     * @return mixed 返回操作结果，如果不需要等待则返回null
     */
    private function base(string $action, array $params = [], bool $wait = false): mixed
    {
        // 创建一个唯一的会话ID作为标识
        $echo = session_create_id();
        // 将操作、参数和标识编码为JSON并加入队列
        Queue::getInstance('napcat')->enqueue(json_encode(['action' => $action, 'params' => $params, 'echo' => $wait ? $echo : ''], JSON_UNESCAPED_UNICODE));
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
}
