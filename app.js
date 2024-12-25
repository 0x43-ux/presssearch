// app.js

document.getElementById('fetch-btn').addEventListener('click', fetchNews);
document.getElementById('copy-btn').addEventListener('click', copyToClipboard);
document.getElementById('export-btn').addEventListener('click', exportToEmail);

let newsData = [];

// 날짜와 시간을 YYYY-MM-DDTHH:MM 형식으로 로컬 시간대로 포맷팅하는 함수
function formatDateLocal(date) {
    const pad = (num) => num.toString().padStart(2, '0');
    const year = date.getFullYear();
    const month = pad(date.getMonth() + 1); // 월은 0부터 시작
    const day = pad(date.getDate());
    const hours = pad(date.getHours());
    const minutes = pad(date.getMinutes());
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function fetchNews() {
    const queryInput = document.getElementById('query').value.trim();
    const from_time = document.getElementById('from_time').value;
    const to_time = document.getElementById('to_time').value;

    // 쉼표로 구분된 검색어를 배열로 변환하고 공백 제거
    const queries = queryInput.split(',').map(q => q.trim()).filter(q => q.length > 0);

    // 기본 검색어 설정
    const defaultQuery = '한국농촌경제연구원';
    const finalQueries = queries.length > 0 ? queries : [defaultQuery];

    fetch('fetch_news.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ queries: finalQueries, from_time, to_time })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            return;
        }
        newsData = data;
        displayNews(finalQueries); // 여러 검색어 전달
        updateSummary(finalQueries, from_time, to_time, newsData.length); // 요약 메시지 업데이트
    })
    .catch(error => {
        console.error('Error:', error);
        alert('뉴스를 가져오는 중 오류가 발생했습니다.');
    });
}

function displayNews(queries) {
    const newsList = document.getElementById('news-list');
    newsList.innerHTML = '';

    if (newsData.length === 0) {
        newsList.innerHTML = '<p>검색 결과가 없습니다.</p>';
        return;
    }

    newsData.forEach((item, index) => {
        const newsDiv = document.createElement('div');
        newsDiv.className = 'news-item';

        // 뉴스 제목
        const title = document.createElement('a');
        title.href = item.link;
        title.target = '_blank';
        title.innerHTML = item.title;

        // 삭제 버튼
        const deleteBtn = document.createElement('span');
        deleteBtn.textContent = '삭제';
        deleteBtn.className = 'delete-btn';
        deleteBtn.onclick = () => deleteNews(index);

        // 키워드가 포함된 문장 표시
        const summariesDiv = document.createElement('div');
        if (Array.isArray(item.matching_sentences) && item.matching_sentences.length > 0) {
            item.matching_sentences.forEach(sentence => {
                const summary = document.createElement('p');
                const boldedSentence = applyBoldToKeywords(sentence, queries);
                summary.innerHTML = boldedSentence;
                summariesDiv.appendChild(summary);
            });
        } else {
            const noMatchMessage = document.createElement('p');
            noMatchMessage.textContent = '키워드가 포함된 문장이 없습니다.';
            summariesDiv.appendChild(noMatchMessage);
        }

        // 신문사 및 발행일 정보 표시
        const publisher = document.createElement('p');
        publisher.textContent = `신문사: ${item.publisher}`;
        publisher.className = 'publisher';

        const pubDate = document.createElement('p');
        pubDate.textContent = `발행일: ${item.pubDate}`;
        pubDate.className = 'pub-date';

        // 뉴스 항목 추가
        newsDiv.appendChild(title);
        newsDiv.appendChild(deleteBtn);
        newsDiv.appendChild(summariesDiv);
        newsDiv.appendChild(publisher);
        newsDiv.appendChild(pubDate);

        newsList.appendChild(newsDiv);
    });
}

function deleteNews(index) {
    if (confirm('해당 뉴스를 삭제하시겠습니까?')) {
        newsData.splice(index, 1);
        displayNews(getAllKeywords());
        updateSummary(getAllKeywords(), document.getElementById('from_time').value, document.getElementById('to_time').value, newsData.length);
    }
}

function applyBoldToKeywords(text, keywords) {
    if (typeof text !== 'string') {
        console.warn('applyBoldToKeywords received non-string text:', text);
        return '';
    }
    keywords.forEach(keyword => {
        if (keyword.length === 0) return;
        const regex = new RegExp(`(${escapeRegExp(keyword)})`, 'gi');
        text = text.replace(regex, '<span class="keyword-highlight">$1</span>');
    });
    return text;
}

function copyToClipboard() {
    if (newsData.length === 0) {
        alert('복사할 뉴스가 없습니다.');
        return;
    }

    // 클립보드에 복사할 내용 생성
    let clipboardContent = '';
    newsData.forEach(item => {
        clipboardContent += `${item.title} (${item.link})\n- ${item.publisher}\n\n`;
    });

    // 클립보드 복사
    navigator.clipboard.writeText(clipboardContent)
        .then(() => alert('뉴스 목록이 클립보드에 복사되었습니다.'))
        .catch(err => {
            console.error('Error copying to clipboard:', err);
            alert('클립보드 복사 중 오류가 발생했습니다.');
        });
}

function exportToEmail() {
    if (newsData.length === 0) {
        alert('내보낼 뉴스가 없습니다.');
        return;
    }

    // 이메일 내용 생성
    let emailContent = '';
    newsData.forEach(item => {
        emailContent += `${item.title} (${item.link})\n- ${item.publisher}\n\n`;
    });

    // 메일 제목과 본문 내용
    const subject = encodeURIComponent('선택한 뉴스 목록');
    const body = encodeURIComponent(emailContent);

    // mailto 링크 생성
    const mailtoLink = `mailto:?subject=${subject}&body=${body}`;

    // 메일 클라이언트 열기
    window.location.href = mailtoLink;
}

function getAllKeywords() {
    const queryInput = document.getElementById('query').value.trim();
    return queryInput.split(',').map(q => q.trim()).filter(q => q.length > 0);
}

// 요약 메시지를 업데이트하는 함수
function updateSummary(queries, from_time, to_time, count) {
    const summaryDiv = document.getElementById('summary');

    // from_time과 to_time을 보기 좋은 형식으로 변환
    const fromDate = formatDisplayDate(from_time);
    const toDate = formatDisplayDate(to_time);

    const formattedQueries = queries.length > 1 ? queries.join(', ') : queries[0];
    summaryDiv.textContent = `선택한 기간 동안 "${formattedQueries}"에 대한 뉴스는 총 ${count}개가 있습니다.`;
}

// 날짜를 보기 좋은 형식으로 변환하는 함수 (예: 2024-12-24T10:00 -> 2024-12-24 10:00)
function formatDisplayDate(dateTimeLocal) {
    if (!dateTimeLocal) return '';
    return dateTimeLocal.replace('T', ' ');
}

// 정규 표현식에서 특수 문자를 이스케이프하는 함수
function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// 페이지 로드 시 기본 시간대 설정
window.onload = function() {
    const fromInput = document.getElementById('from_time');
    const toInput = document.getElementById('to_time');

    const current = new Date();
    const toTime = formatDateLocal(current);
    toInput.value = toTime;

    const yesterday = new Date(current);
    yesterday.setDate(yesterday.getDate() - 1);
    yesterday.setHours(0, 0, 0, 0);
    const fromTime = formatDateLocal(yesterday);
    fromInput.value = fromTime;
};
